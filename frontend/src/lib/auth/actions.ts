"use server";

import { z } from "zod";
import {
  clearSessionCookie,
  getCurrentUser,
  getToken,
  setSessionCookie,
} from "./session";
import { backendFetch, enforceTwoFactor, extractDetail } from "@/lib/api/backend";
import { apiPath, resourceIdSchema } from "@/lib/api/path";
import type { ApiPostResult } from "@/lib/api/client";
import type {
  ProfileUpdatePayload,
  SessionUser,
  SessionComment,
  SessionInvoice,
  SessionTask,
  SessionTimeEntry,
  TaskStatus,
  CandidateMessage,
  AvailableProject,
} from "@/lib/types";

/**
 * Message exact levé par App\Security\UserChecker::checkPostAuth() quand le
 * compte n'est pas vérifié — cette vérification s'exécute APRÈS la validation
 * du mot de passe (cf. commentaire de UserChecker), donc ce message n'est
 * jamais atteignable sans avoir déjà prouvé connaître le bon mot de passe :
 * le distinguer du cas "identifiants invalides" ne fuite rien à un tiers qui
 * ne connaît pas le mot de passe (pas de régression anti-énumération), et
 * permet de guider spécifiquement un utilisateur légitime bloqué. Couplage
 * fragile mais volontaire au texte backend ; en cas de désaccord, le pire
 * échec possible est un simple retour au message générique (fail-safe).
 */
const ACCOUNT_NOT_VERIFIED_MESSAGE = "Votre compte n'est pas encore vérifié. Consultez vos emails pour l'activer.";

/**
 * Chaque export de ce fichier est un endpoint HTTP public (Server Action) :
 * ses arguments arrivent du navigateur sans garantie de type malgré la
 * signature TypeScript. Tout est donc revalidé ici avant l'appel backend —
 * en particulier les identifiants insérés dans des chemins d'API (anti-
 * traversée, cf. src/lib/api/path.ts) — et chaque appel transmet l'IP/UA du
 * vrai visiteur (backendFetch → forwardedVisitorHeaders).
 */
const invalidInput = { ok: false as const, error: "invalid_input" };

const shortString = (max: number) => z.string().max(max);
const emailInput = z.string().trim().min(1).max(180);
const passwordInput = z.string().min(1).max(4096);
const TASK_STATUSES = ["todo", "in_progress", "done", "blocked"] as const satisfies readonly TaskStatus[];

/** Champs auto-éditables — miroir de MeController::EDITABLE_FIELDS (et bornes de longueur backend). */
const profileUpdateSchema = z
  .object({
    fullName: shortString(255),
    bio: shortString(2000),
    specialties: z.array(shortString(60)).max(20),
    availability: shortString(100),
    portfolioUrl: shortString(255),
    yearsOfExperience: z.number().min(0).max(60).transform(Math.trunc).nullable(),
    city: shortString(100),
    linkedinUrl: shortString(255),
    githubUrl: shortString(255),
    languages: z.array(shortString(60)).max(20),
  })
  .partial()
  .strict();

const logTimeSchema = z
  .object({
    minutes: z.number().int().positive().max(60 * 24 * 31),
    spentOn: z.string().regex(/^\d{4}-\d{2}-\d{2}$/).optional(),
    description: shortString(1000).optional(),
    taskId: resourceIdSchema.optional(),
  })
  .strict();

type Mutation<T> = { ok: true; data: T } | { ok: false; error: string };

/**
 * Appel authentifié commun aux mutations de l'espace compte :
 *  - `unauthenticated` sans cookie de session ;
 *  - `network_error` en cas d'échec réseau/délai ;
 *  - redirection vers la mise en place de la 2FA si le backend répond 403
 *    `2fa_required` (hors try/catch : `redirect()` lève NEXT_REDIRECT) ;
 *  - `detail` backend (ou `HTTP <status>`) sinon.
 */
async function authedMutation<T>(
  label: string,
  path: string,
  init: { method: string; json?: unknown },
  parse: (res: Response) => Promise<T>,
): Promise<Mutation<T>> {
  const token = await getToken();
  if (!token) {
    return { ok: false, error: "unauthenticated" };
  }

  let res: Response;
  try {
    res = await backendFetch(path, { method: init.method, token, json: init.json });
  } catch (error) {
    console.error(`[auth] ${label} failed`, error);
    return { ok: false, error: "network_error" };
  }

  await enforceTwoFactor(res);

  if (!res.ok) {
    const detail = await extractDetail(res);
    return { ok: false, error: detail ?? `HTTP ${res.status}` };
  }

  try {
    return { ok: true, data: await parse(res) };
  } catch (error) {
    console.error(`[auth] ${label} invalid response`, error);
    return { ok: false, error: "network_error" };
  }
}

const ignoreBody = async () => undefined;
const jsonBody = <T>() => async (res: Response) => (await res.json()) as T;

/** Appel public (sans jeton) : POST JSON + en-têtes visiteur ; `detail` backend en cas d'erreur. */
async function publicPost(label: string, path: string, json: unknown): Promise<ApiPostResult> {
  try {
    const res = await backendFetch(path, { method: "POST", json });
    if (!res.ok) {
      const detail = await extractDetail(res);
      return { ok: false, error: detail ?? `HTTP ${res.status}` };
    }
    return { ok: true };
  } catch (error) {
    console.error(`[auth] ${label} failed`, error);
    return { ok: false, error: "network_error" };
  }
}

export type LoginResult =
  | { ok: true; requiresTwoFactor: false }
  | { ok: true; requiresTwoFactor: true; challengeToken: string }
  | { ok: false; error: string };

/**
 * Connexion : POST /api/login_check (lexik JWT). En cas de succès direct, le
 * token est posé dans un cookie httpOnly. Le mot de passe ne transite que
 * côté serveur.
 *
 * Un compte protégé par la double authentification n'obtient pas son jeton
 * ici : le backend répond { twoFactorRequired: true, challengeToken } à la
 * place (App\Security\Api\TwoFactorAwareJwtSuccessHandler) — l'appelant doit
 * alors demander le code à 6 chiffres et appeler verifyLoginTwoFactor()
 * ci-dessous pour obtenir le vrai jeton.
 */
export async function login(
  email: string,
  password: string,
): Promise<LoginResult> {
  const parsed = z.object({ email: emailInput, password: passwordInput }).safeParse({ email, password });
  if (!parsed.success) return { ok: false, error: "invalid_credentials" };

  try {
    const res = await backendFetch("/login_check", { method: "POST", json: parsed.data });

    if (!res.ok) {
      if (res.status !== 401) {
        return { ok: false, error: `HTTP ${res.status}` };
      }

      // lexik renvoie 401 { message: "Invalid credentials." } pour un email ou
      // mot de passe erroné ; on ne fuite pas ce détail. Seul le message
      // spécifique "compte non vérifié" (voir ACCOUNT_NOT_VERIFIED_MESSAGE)
      // est distingué, car il ne peut être atteint qu'après un mot de passe
      // correct.
      const message = await res
        .json()
        .then((b: unknown) => (typeof b === "object" && b && "message" in b ? String((b as { message: unknown }).message) : null))
        .catch(() => null);

      return { ok: false, error: message === ACCOUNT_NOT_VERIFIED_MESSAGE ? "not_verified" : "invalid_credentials" };
    }

    const body = (await res.json()) as { token?: string; twoFactorRequired?: boolean; challengeToken?: string };

    if (body.twoFactorRequired) {
      if (!body.challengeToken) {
        return { ok: false, error: "no_challenge_token" };
      }

      return { ok: true, requiresTwoFactor: true, challengeToken: body.challengeToken };
    }

    if (!body.token) {
      return { ok: false, error: "no_token" };
    }

    await setSessionCookie(body.token);
    return { ok: true, requiresTwoFactor: false };
  } catch (error) {
    console.error("[auth] login failed", error);
    return { ok: false, error: "network_error" };
  }
}

/**
 * Second temps de la connexion pour un compte 2FA : POST /api/login_check/2fa
 * avec le jeton de défi (obtenu via login() ci-dessus) et le code à 6 chiffres
 * (ou un code de récupération). En cas de succès, pose le vrai jeton comme
 * login() le ferait directement.
 */
export async function verifyLoginTwoFactor(
  challengeToken: string,
  code: string,
): Promise<ApiPostResult> {
  const parsed = z
    .object({ challengeToken: shortString(4096).min(1), code: shortString(64).min(1) })
    .safeParse({ challengeToken, code });
  if (!parsed.success) return { ok: false, error: "invalid_code" };

  try {
    const res = await backendFetch("/login_check/2fa", { method: "POST", json: parsed.data });

    if (!res.ok) {
      if (res.status === 429) {
        return { ok: false, error: "too_many_attempts" };
      }
      if (res.status === 401) {
        return { ok: false, error: "invalid_code" };
      }

      return { ok: false, error: `HTTP ${res.status}` };
    }

    const body = (await res.json()) as { token?: string };
    if (!body.token) {
      return { ok: false, error: "no_token" };
    }

    await setSessionCookie(body.token);
    return { ok: true };
  } catch (error) {
    console.error("[auth] verifyLoginTwoFactor failed", error);
    return { ok: false, error: "network_error" };
  }
}

/**
 * Déconnexion : révoque d'abord le JWT côté backend (POST /api/logout — sans
 * ça, un jeton volé resterait valable jusqu'à son expiration naturelle), puis
 * supprime le cookie de session DANS TOUS LES CAS. Tolérant aux erreurs :
 * backend injoignable, 401 (jeton déjà expiré) ou 404 (endpoint pas encore
 * déployé) n'empêchent jamais la déconnexion locale.
 */
export async function logout(): Promise<void> {
  const token = await getToken();

  if (token) {
    try {
      const res = await backendFetch("/logout", { method: "POST", token, timeoutMs: 5000 });
      if (!res.ok && res.status !== 401 && res.status !== 404) {
        console.warn(`[auth] POST /logout -> ${res.status}`);
      }
    } catch (error) {
      console.error("[auth] POST /logout failed", error);
    }
  }

  await clearSessionCookie();
}

/**
 * Statut de connexion pour les composants clients (ex. en-tête). Appelée
 * après le montage plutôt que lue via `cookies()` pendant le rendu : sinon
 * le layout partagé forcerait toutes les pages (y compris `force-static`)
 * en rendu dynamique.
 */
export async function getAuthStatus(): Promise<boolean> {
  const user = await getCurrentUser();
  return Boolean(user);
}

/**
 * Auto-édition du profil : PATCH /api/me (Bearer token). Le backend applique une
 * liste blanche stricte de champs et refuse toute élévation de privilèges ;
 * on la reproduit ici (schéma strict : tout autre champ — mot de passe,
 * rôles… — est refusé avant l'appel).
 */
export async function updateProfile(
  payload: ProfileUpdatePayload,
): Promise<ApiPostResult & { user?: SessionUser }> {
  const parsed = profileUpdateSchema.safeParse(payload);
  if (!parsed.success) return invalidInput;

  const result = await authedMutation("updateProfile", "/me", { method: "PATCH", json: parsed.data }, jsonBody<SessionUser>());
  return result.ok ? { ok: true, user: result.data } : result;
}

/**
 * Changement de mot de passe connecté : PATCH /api/me avec `currentPassword` +
 * `plainPassword`. Le backend exige la preuve de l'ancien mot de passe (cf.
 * MeController::update()) — un token intercepté ne doit pas suffire à
 * verrouiller le titulaire légitime hors de son compte.
 */
export async function changePassword(
  currentPassword: string,
  plainPassword: string,
): Promise<ApiPostResult> {
  const parsed = z
    .object({ currentPassword: passwordInput, plainPassword: passwordInput })
    .safeParse({ currentPassword, plainPassword });
  if (!parsed.success) return invalidInput;

  const result = await authedMutation(
    "changePassword",
    "/me",
    { method: "PATCH", json: parsed.data },
    jsonBody<{ token?: unknown }>(),
  );
  if (!result.ok) return result;

  // Le backend révoque TOUS les jetons du compte au changement de mot de
  // passe (y compris celui de cette requête) et renvoie un nouveau jeton
  // pour cet appareil : on remplace le cookie, sinon la session tomberait
  // à la requête suivante. Sans jeton valide renvoyé, on efface la session
  // (reconnexion propre plutôt qu'un cookie révoqué).
  const token = result.data?.token;
  if (typeof token === "string" && /^[\w-]+\.[\w-]+\.[\w-]+$/.test(token)) {
    await setSessionCookie(token);
  } else {
    await clearSessionCookie();
  }
  return { ok: true };
}

/**
 * Renvoie l'email de vérification du compte courant : POST /api/me/resend-verification.
 * Le backend refuse (409) si le compte est déjà vérifié.
 */
export async function resendVerificationEmail(): Promise<ApiPostResult> {
  const result = await authedMutation("resendVerificationEmail", "/me/resend-verification", { method: "POST" }, ignoreBody);
  return result.ok ? { ok: true } : result;
}

export type TwoFactorSetupResult =
  | { ok: true; secret: string; qrCodeDataUri: string }
  | { ok: false; error: string };

/**
 * Démarre l'activation de la 2FA : POST /api/me/2fa/setup. Ne persiste rien
 * côté serveur — le secret retourné doit être renvoyé tel quel à
 * confirmTwoFactorSetup() ci-dessous pour être effectivement activé.
 * (Exempté de la règle `2fa_required` côté backend : pas de redirection ici.)
 */
export async function setupTwoFactor(): Promise<TwoFactorSetupResult> {
  const token = await getToken();
  if (!token) {
    return { ok: false, error: "unauthenticated" };
  }

  try {
    const res = await backendFetch("/me/2fa/setup", { method: "POST", token });

    if (!res.ok) {
      return { ok: false, error: `HTTP ${res.status}` };
    }

    const body = (await res.json()) as { secret?: string; qrCodeDataUri?: string };
    if (!body.secret || !body.qrCodeDataUri) {
      return { ok: false, error: "invalid_response" };
    }

    return { ok: true, secret: body.secret, qrCodeDataUri: body.qrCodeDataUri };
  } catch (error) {
    console.error("[auth] setupTwoFactor failed", error);
    return { ok: false, error: "network_error" };
  }
}

export type TwoFactorConfirmResult =
  | { ok: true; recoveryCodes: string[] }
  | { ok: false; error: string };

/**
 * Termine l'activation de la 2FA : POST /api/me/2fa/confirm avec le secret
 * obtenu via setupTwoFactor(), le code à 6 chiffres saisi par l'utilisateur
 * et son mot de passe actuel (exigé par le backend : un Bearer token
 * intercepté ne doit pas suffire à lier un appareil TOTP tiers au compte).
 * En cas de succès, renvoie les codes de récupération — affichés une seule
 * fois, l'appelant doit les faire noter avant de continuer.
 */
export async function confirmTwoFactorSetup(
  secret: string,
  code: string,
  currentPassword: string,
): Promise<TwoFactorConfirmResult> {
  const parsed = z
    .object({ secret: shortString(256).min(1), code: shortString(64).min(1), currentPassword: passwordInput })
    .safeParse({ secret, code, currentPassword });
  if (!parsed.success) return { ok: false, error: "invalid_code" };

  const token = await getToken();
  if (!token) {
    return { ok: false, error: "unauthenticated" };
  }

  try {
    const res = await backendFetch("/me/2fa/confirm", { method: "POST", token, json: parsed.data });

    if (!res.ok) {
      if (res.status === 429) {
        return { ok: false, error: "too_many_attempts" };
      }
      if (res.status === 401) {
        return { ok: false, error: "invalid_code" };
      }
      // 422 : mot de passe actuel incorrect (cf. ApiTwoFactorSetupController::confirm).
      if (res.status === 422) {
        return { ok: false, error: "invalid_password" };
      }

      return { ok: false, error: `HTTP ${res.status}` };
    }

    const body = (await res.json()) as { recoveryCodes?: string[] };
    if (!body.recoveryCodes) {
      return { ok: false, error: "invalid_response" };
    }

    return { ok: true, recoveryCodes: body.recoveryCodes };
  } catch (error) {
    console.error("[auth] confirmTwoFactorSetup failed", error);
    return { ok: false, error: "network_error" };
  }
}

/**
 * Demande de réinitialisation de mot de passe : POST /api/forgot-password.
 * Le backend répond toujours succès (anti-énumération), que le compte existe ou non.
 */
export async function requestPasswordReset(email: string): Promise<ApiPostResult> {
  const parsed = emailInput.safeParse(email);
  if (!parsed.success) return invalidInput;

  return publicPost("requestPasswordReset", "/forgot-password", { email: parsed.data });
}

/** Confirmation de la réinitialisation : POST /api/reset-password (token à usage unique). */
export async function confirmPasswordReset(
  token: string,
  plainPassword: string,
): Promise<ApiPostResult> {
  const parsed = z
    .object({ token: shortString(512).min(1), plainPassword: passwordInput })
    .safeParse({ token, plainPassword });
  if (!parsed.success) return invalidInput;

  return publicPost("confirmPasswordReset", "/reset-password", parsed.data);
}

/**
 * Consomme le token de vérification d'email : POST /api/verify-email.
 * Contrepartie frontend de App\Controller\Api\EmailVerificationController::confirm()
 * — pour un compte client/freelance, le lien envoyé par email pointe
 * désormais ici plutôt que vers la route Symfony /verif/{token} (cf.
 * App\Service\AccountLinkResolver), afin de rester dans l'espace Next.js.
 */
export async function verifyEmailToken(token: string): Promise<ApiPostResult> {
  const parsed = shortString(512).min(1).safeParse(token);
  if (!parsed.success) return invalidInput;

  return publicPost("verifyEmailToken", "/verify-email", { token: parsed.data });
}

/**
 * Demande un nouveau lien de vérification d'email sans être connecté : POST
 * /api/resend-verification-email. Nécessaire car la connexion (web ET JWT)
 * est bloquée pour un compte non vérifié (cf. App\Security\UserChecker côté
 * backend) — la seule action `resendVerificationEmail()` déjà existante dans
 * ce fichier exige un token, donc inutilisable tant que le compte n'est pas
 * vérifié. Le backend répond toujours succès (anti-énumération), que le
 * compte existe ou non, ou soit déjà vérifié.
 */
export async function requestVerificationEmail(email: string): Promise<ApiPostResult> {
  const parsed = emailInput.safeParse(email);
  if (!parsed.success) return invalidInput;

  return publicPost("requestVerificationEmail", "/resend-verification-email", { email: parsed.data });
}

/**
 * Poste un message dans le fil de discussion d'un projet : POST
 * /api/me/projects/{id}/comments. Même périmètre d'accès que la lecture
 * (client, responsable ou collaborateur du projet).
 */
export async function postProjectComment(
  projectId: number,
  content: string,
): Promise<ApiPostResult & { comment?: SessionComment }> {
  const parsed = z
    .object({ projectId: resourceIdSchema, content: shortString(5000).min(1) })
    .safeParse({ projectId, content });
  if (!parsed.success) return invalidInput;

  const result = await authedMutation(
    "postProjectComment",
    apiPath`/me/projects/${parsed.data.projectId}/comments`,
    { method: "POST", json: { content: parsed.data.content } },
    jsonBody<SessionComment>(),
  );
  return result.ok ? { ok: true, comment: result.data } : result;
}

/**
 * Changement de statut d'une tâche assignée au collaborateur courant :
 * POST /api/me/projects/{id}/tasks/{taskId}/status. Renvoie
 * error: "freelance_profile_incomplete" (via le champ `detail` du backend)
 * si le profil freelance n'est pas complet à 100 % — l'appelant décide de
 * l'affichage, aucun traitement spécial nécessaire ici.
 */
export async function updateTaskStatus(
  projectId: number,
  taskId: number,
  status: TaskStatus,
): Promise<ApiPostResult & { task?: SessionTask }> {
  const parsed = z
    .object({ projectId: resourceIdSchema, taskId: resourceIdSchema, status: z.enum(TASK_STATUSES) })
    .safeParse({ projectId, taskId, status });
  if (!parsed.success) return invalidInput;

  const result = await authedMutation(
    "updateTaskStatus",
    apiPath`/me/projects/${parsed.data.projectId}/tasks/${parsed.data.taskId}/status`,
    { method: "POST", json: { status: parsed.data.status } },
    jsonBody<SessionTask>(),
  );
  return result.ok ? { ok: true, task: result.data } : result;
}

/**
 * Saisie de temps sur un projet par le collaborateur courant :
 * POST /api/me/projects/{id}/time-entries.
 */
export async function logTime(
  projectId: number,
  payload: { minutes: number; spentOn?: string; description?: string; taskId?: number },
): Promise<ApiPostResult & { entry?: SessionTimeEntry }> {
  const id = resourceIdSchema.safeParse(projectId);
  const body = logTimeSchema.safeParse(payload);
  if (!id.success || !body.success) return invalidInput;

  const result = await authedMutation(
    "logTime",
    apiPath`/me/projects/${id.data}/time-entries`,
    { method: "POST", json: body.data },
    jsonBody<SessionTimeEntry>(),
  );
  return result.ok ? { ok: true, entry: result.data } : result;
}

/**
 * Le client confirme être d'accord avec le montant d'une facture :
 * POST /api/me/invoices/{id}/validate.
 */
export async function validateInvoice(
  invoiceId: number,
): Promise<ApiPostResult & { invoice?: SessionInvoice }> {
  const id = resourceIdSchema.safeParse(invoiceId);
  if (!id.success) return invalidInput;

  const result = await authedMutation(
    "validateInvoice",
    apiPath`/me/invoices/${id.data}/validate`,
    { method: "POST" },
    jsonBody<SessionInvoice>(),
  );
  return result.ok ? { ok: true, invoice: result.data } : result;
}

/**
 * Le client demande une révision du montant d'une facture — le motif est
 * posté dans le fil de discussion du projet : POST
 * /api/me/invoices/{id}/request-revision.
 */
export async function requestInvoiceRevision(
  invoiceId: number,
  message: string,
): Promise<ApiPostResult & { invoice?: SessionInvoice }> {
  const parsed = z
    .object({ invoiceId: resourceIdSchema, message: shortString(2000).min(1) })
    .safeParse({ invoiceId, message });
  if (!parsed.success) return invalidInput;

  const result = await authedMutation(
    "requestInvoiceRevision",
    apiPath`/me/invoices/${parsed.data.invoiceId}/request-revision`,
    { method: "POST", json: { message: parsed.data.message } },
    jsonBody<SessionInvoice>(),
  );
  return result.ok ? { ok: true, invoice: result.data } : result;
}

/**
 * Suppression (anonymisation) du compte courant : DELETE /api/me. Efface le
 * cookie de session localement dans tous les cas où l'appel réussit — un
 * compte supprimé ne doit plus être considéré comme connecté.
 */
export async function deleteAccount(currentPassword: string): Promise<ApiPostResult> {
  // Action irréversible : le backend exige le mot de passe actuel (un jeton
  // volé ne doit pas suffire à effacer le compte, cf. MeController::delete()).
  const parsed = z.object({ currentPassword: passwordInput }).safeParse({ currentPassword });
  if (!parsed.success) return invalidInput;

  const result = await authedMutation("deleteAccount", "/me", { method: "DELETE", json: parsed.data }, ignoreBody);
  if (!result.ok) return result;

  await clearSessionCookie();
  return { ok: true };
}

/**
 * Fil de conversation candidat <-> admin : GET /api/me/messages. La
 * consultation vaut accusé de lecture côté backend (CandidateMessageRepository::markReadFor),
 * d'où `cache: "no-store"` (jamais de cache HTTP entre deux appels, sinon un
 * badge "non lu" resterait affiché après lecture).
 */
export async function getCandidateMessages(): Promise<CandidateMessage[] | null> {
  const result = await authedMutation(
    "getCandidateMessages",
    "/me/messages",
    { method: "GET" },
    jsonBody<{ messages: CandidateMessage[] }>(),
  );
  return result.ok ? result.data.messages : null;
}

export async function sendCandidateMessage(
  body: string,
): Promise<ApiPostResult & { message?: CandidateMessage }> {
  const parsed = shortString(5000).min(1).safeParse(body);
  if (!parsed.success) return invalidInput;

  const result = await authedMutation(
    "sendCandidateMessage",
    "/me/messages",
    { method: "POST", json: { body: parsed.data } },
    jsonBody<CandidateMessage>(),
  );
  return result.ok ? { ok: true, message: result.data } : result;
}

/**
 * Demande d'auto-association à un projet "à venir" (espace
 * /compte/projets-disponibles) : POST /api/me/projects/{id}/join. Ne donne
 * AUCUN accès immédiat — crée une demande "en attente" que seul un admin peut
 * valider depuis le back-office ; la réponse renvoie `joinPending: true`.
 * Le backend renvoie 403 avec error: "freelance_profile_incomplete" si le
 * profil freelance n'est pas complet à 100 % (ne devrait pas arriver ici : la
 * page ne rend le bouton "Rejoindre" que si SessionUser.profileCompletion
 * === 100, mais le backend reste la source de vérité en cas de course avec
 * une modification de profil dans un autre onglet).
 */
export async function joinProject(
  projectId: number,
): Promise<ApiPostResult & { project?: AvailableProject }> {
  const id = resourceIdSchema.safeParse(projectId);
  if (!id.success) return invalidInput;

  const result = await authedMutation(
    "joinProject",
    apiPath`/me/projects/${id.data}/join`,
    { method: "POST" },
    jsonBody<AvailableProject>(),
  );
  return result.ok ? { ok: true, project: result.data } : result;
}
