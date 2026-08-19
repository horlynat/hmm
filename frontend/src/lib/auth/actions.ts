"use server";

import { headers } from "next/headers";
import { API_URL } from "./config";
import {
  clearSessionCookie,
  getCurrentUser,
  getToken,
  setSessionCookie,
} from "./session";
import type { ApiPostResult } from "@/lib/api/client";
import type {
  ProfileUpdatePayload,
  SessionUser,
  SessionComment,
  SessionInvoice,
  SessionTask,
  SessionTimeEntry,
  TaskStatus,
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
 * IP + User-Agent du vrai visiteur, à transmettre explicitement aux appels
 * backend liés à la connexion (login_check, 2fa). Sans ça, le backend ne
 * voit que la requête serveur-à-serveur émise par CE conteneur Next.js
 * (172.18.0.x) : LoginHistory/UserSession enregistraient donc son IP interne
 * (jamais géolocalisable) et son User-Agent par défaut — que
 * matomo/device-detector classe comme "Robot (Generic Bot)" côté admin,
 * laissant croire à tort qu'un bot s'était connecté à la place du client.
 * SYMFONY_TRUSTED_PROXIES couvre déjà le réseau Docker interne (cf.
 * main/infra/README.md) : le backend fait confiance à ces en-têtes venant de
 * ce conteneur. Même pattern que app/api/ai-assistant/chat/route.ts.
 */
async function forwardedVisitorHeaders(): Promise<Record<string, string>> {
  const h = await headers();
  const forwardedFor = h.get("x-forwarded-for") ?? h.get("x-real-ip") ?? "";
  const userAgent = h.get("user-agent") ?? "";

  return {
    ...(forwardedFor ? { "X-Forwarded-For": forwardedFor } : {}),
    ...(userAgent ? { "User-Agent": userAgent } : {}),
  };
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
  try {
    const res = await fetch(`${API_URL}/login_check`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        ...(await forwardedVisitorHeaders()),
      },
      body: JSON.stringify({ email, password }),
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

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
  try {
    const res = await fetch(`${API_URL}/login_check/2fa`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        ...(await forwardedVisitorHeaders()),
      },
      body: JSON.stringify({ challengeToken, code }),
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

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

/** Déconnexion : supprime le cookie de session. */
export async function logout(): Promise<void> {
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
 * liste blanche stricte de champs et refuse toute élévation de privilèges.
 */
export async function updateProfile(
  payload: ProfileUpdatePayload,
): Promise<ApiPostResult & { user?: SessionUser }> {
  const token = await getToken();
  if (!token) {
    return { ok: false, error: "unauthenticated" };
  }

  try {
    const res = await fetch(`${API_URL}/me`, {
      method: "PATCH",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify(payload),
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) {
      const detail = await res
        .json()
        .then((body: unknown) =>
          typeof body === "object" && body && "detail" in body
            ? String((body as { detail: unknown }).detail)
            : null,
        )
        .catch(() => null);
      return { ok: false, error: detail ?? `HTTP ${res.status}` };
    }

    const user = (await res.json()) as SessionUser;
    return { ok: true, user };
  } catch (error) {
    console.error("[auth] updateProfile failed", error);
    return { ok: false, error: "network_error" };
  }
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
  const token = await getToken();
  if (!token) {
    return { ok: false, error: "unauthenticated" };
  }

  try {
    const res = await fetch(`${API_URL}/me`, {
      method: "PATCH",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ currentPassword, plainPassword }),
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) {
      const detail = await res
        .json()
        .then((body: unknown) =>
          typeof body === "object" && body && "detail" in body
            ? String((body as { detail: unknown }).detail)
            : null,
        )
        .catch(() => null);
      return { ok: false, error: detail ?? `HTTP ${res.status}` };
    }

    return { ok: true };
  } catch (error) {
    console.error("[auth] changePassword failed", error);
    return { ok: false, error: "network_error" };
  }
}

/**
 * Renvoie l'email de vérification du compte courant : POST /api/me/resend-verification.
 * Le backend refuse (409) si le compte est déjà vérifié.
 */
export async function resendVerificationEmail(): Promise<ApiPostResult> {
  const token = await getToken();
  if (!token) {
    return { ok: false, error: "unauthenticated" };
  }

  try {
    const res = await fetch(`${API_URL}/me/resend-verification`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) {
      const detail = await res
        .json()
        .then((body: unknown) =>
          typeof body === "object" && body && "detail" in body
            ? String((body as { detail: unknown }).detail)
            : null,
        )
        .catch(() => null);
      return { ok: false, error: detail ?? `HTTP ${res.status}` };
    }

    return { ok: true };
  } catch (error) {
    console.error("[auth] resendVerificationEmail failed", error);
    return { ok: false, error: "network_error" };
  }
}

export type TwoFactorSetupResult =
  | { ok: true; secret: string; qrCodeDataUri: string }
  | { ok: false; error: string };

/**
 * Démarre l'activation de la 2FA : POST /api/me/2fa/setup. Ne persiste rien
 * côté serveur — le secret retourné doit être renvoyé tel quel à
 * confirmTwoFactorSetup() ci-dessous pour être effectivement activé.
 */
export async function setupTwoFactor(): Promise<TwoFactorSetupResult> {
  const token = await getToken();
  if (!token) {
    return { ok: false, error: "unauthenticated" };
  }

  try {
    const res = await fetch(`${API_URL}/me/2fa/setup`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

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
 * obtenu via setupTwoFactor() et le code à 6 chiffres saisi par l'utilisateur.
 * En cas de succès, renvoie les codes de récupération — affichés une seule
 * fois, l'appelant doit les faire noter avant de continuer.
 */
export async function confirmTwoFactorSetup(
  secret: string,
  code: string,
): Promise<TwoFactorConfirmResult> {
  const token = await getToken();
  if (!token) {
    return { ok: false, error: "unauthenticated" };
  }

  try {
    const res = await fetch(`${API_URL}/me/2fa/confirm`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ secret, code }),
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) {
      if (res.status === 429) {
        return { ok: false, error: "too_many_attempts" };
      }
      if (res.status === 401) {
        return { ok: false, error: "invalid_code" };
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
  try {
    const res = await fetch(`${API_URL}/forgot-password`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ email }),
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) {
      const detail = await res
        .json()
        .then((body: unknown) =>
          typeof body === "object" && body && "detail" in body
            ? String((body as { detail: unknown }).detail)
            : null,
        )
        .catch(() => null);
      return { ok: false, error: detail ?? `HTTP ${res.status}` };
    }

    return { ok: true };
  } catch (error) {
    console.error("[auth] requestPasswordReset failed", error);
    return { ok: false, error: "network_error" };
  }
}

/** Confirmation de la réinitialisation : POST /api/reset-password (token à usage unique). */
export async function confirmPasswordReset(
  token: string,
  plainPassword: string,
): Promise<ApiPostResult> {
  try {
    const res = await fetch(`${API_URL}/reset-password`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ token, plainPassword }),
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) {
      const detail = await res
        .json()
        .then((body: unknown) =>
          typeof body === "object" && body && "detail" in body
            ? String((body as { detail: unknown }).detail)
            : null,
        )
        .catch(() => null);
      return { ok: false, error: detail ?? `HTTP ${res.status}` };
    }

    return { ok: true };
  } catch (error) {
    console.error("[auth] confirmPasswordReset failed", error);
    return { ok: false, error: "network_error" };
  }
}

/**
 * Consomme le token de vérification d'email : POST /api/verify-email.
 * Contrepartie frontend de App\Controller\Api\EmailVerificationController::confirm()
 * — pour un compte client/freelance, le lien envoyé par email pointe
 * désormais ici plutôt que vers la route Symfony /verif/{token} (cf.
 * App\Service\AccountLinkResolver), afin de rester dans l'espace Next.js.
 */
export async function verifyEmailToken(token: string): Promise<ApiPostResult> {
  try {
    const res = await fetch(`${API_URL}/verify-email`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ token }),
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) {
      const detail = await res
        .json()
        .then((body: unknown) =>
          typeof body === "object" && body && "detail" in body
            ? String((body as { detail: unknown }).detail)
            : null,
        )
        .catch(() => null);
      return { ok: false, error: detail ?? `HTTP ${res.status}` };
    }

    return { ok: true };
  } catch (error) {
    console.error("[auth] verifyEmailToken failed", error);
    return { ok: false, error: "network_error" };
  }
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
  try {
    const res = await fetch(`${API_URL}/resend-verification-email`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ email }),
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) {
      const detail = await res
        .json()
        .then((body: unknown) =>
          typeof body === "object" && body && "detail" in body
            ? String((body as { detail: unknown }).detail)
            : null,
        )
        .catch(() => null);
      return { ok: false, error: detail ?? `HTTP ${res.status}` };
    }

    return { ok: true };
  } catch (error) {
    console.error("[auth] requestVerificationEmail failed", error);
    return { ok: false, error: "network_error" };
  }
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
  const token = await getToken();
  if (!token) {
    return { ok: false, error: "unauthenticated" };
  }

  try {
    const res = await fetch(`${API_URL}/me/projects/${projectId}/comments`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ content }),
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) {
      const detail = await res
        .json()
        .then((body: unknown) =>
          typeof body === "object" && body && "detail" in body
            ? String((body as { detail: unknown }).detail)
            : null,
        )
        .catch(() => null);
      return { ok: false, error: detail ?? `HTTP ${res.status}` };
    }

    const comment = (await res.json()) as SessionComment;
    return { ok: true, comment };
  } catch (error) {
    console.error("[auth] postProjectComment failed", error);
    return { ok: false, error: "network_error" };
  }
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
  const token = await getToken();
  if (!token) {
    return { ok: false, error: "unauthenticated" };
  }

  try {
    const res = await fetch(`${API_URL}/me/projects/${projectId}/tasks/${taskId}/status`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ status }),
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) {
      const detail = await res
        .json()
        .then((body: unknown) =>
          typeof body === "object" && body && "detail" in body
            ? String((body as { detail: unknown }).detail)
            : null,
        )
        .catch(() => null);
      return { ok: false, error: detail ?? `HTTP ${res.status}` };
    }

    const task = (await res.json()) as SessionTask;
    return { ok: true, task };
  } catch (error) {
    console.error("[auth] updateTaskStatus failed", error);
    return { ok: false, error: "network_error" };
  }
}

/**
 * Saisie de temps sur un projet par le collaborateur courant :
 * POST /api/me/projects/{id}/time-entries.
 */
export async function logTime(
  projectId: number,
  payload: { minutes: number; spentOn?: string; description?: string; taskId?: number },
): Promise<ApiPostResult & { entry?: SessionTimeEntry }> {
  const token = await getToken();
  if (!token) {
    return { ok: false, error: "unauthenticated" };
  }

  try {
    const res = await fetch(`${API_URL}/me/projects/${projectId}/time-entries`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify(payload),
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) {
      const detail = await res
        .json()
        .then((body: unknown) =>
          typeof body === "object" && body && "detail" in body
            ? String((body as { detail: unknown }).detail)
            : null,
        )
        .catch(() => null);
      return { ok: false, error: detail ?? `HTTP ${res.status}` };
    }

    const entry = (await res.json()) as SessionTimeEntry;
    return { ok: true, entry };
  } catch (error) {
    console.error("[auth] logTime failed", error);
    return { ok: false, error: "network_error" };
  }
}

/**
 * Le client confirme être d'accord avec le montant d'une facture :
 * POST /api/me/invoices/{id}/validate.
 */
export async function validateInvoice(
  invoiceId: number,
): Promise<ApiPostResult & { invoice?: SessionInvoice }> {
  const token = await getToken();
  if (!token) {
    return { ok: false, error: "unauthenticated" };
  }

  try {
    const res = await fetch(`${API_URL}/me/invoices/${invoiceId}/validate`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) {
      const detail = await res
        .json()
        .then((body: unknown) =>
          typeof body === "object" && body && "detail" in body
            ? String((body as { detail: unknown }).detail)
            : null,
        )
        .catch(() => null);
      return { ok: false, error: detail ?? `HTTP ${res.status}` };
    }

    const invoice = (await res.json()) as SessionInvoice;
    return { ok: true, invoice };
  } catch (error) {
    console.error("[auth] validateInvoice failed", error);
    return { ok: false, error: "network_error" };
  }
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
  const token = await getToken();
  if (!token) {
    return { ok: false, error: "unauthenticated" };
  }

  try {
    const res = await fetch(`${API_URL}/me/invoices/${invoiceId}/request-revision`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ message }),
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) {
      const detail = await res
        .json()
        .then((body: unknown) =>
          typeof body === "object" && body && "detail" in body
            ? String((body as { detail: unknown }).detail)
            : null,
        )
        .catch(() => null);
      return { ok: false, error: detail ?? `HTTP ${res.status}` };
    }

    const invoice = (await res.json()) as SessionInvoice;
    return { ok: true, invoice };
  } catch (error) {
    console.error("[auth] requestInvoiceRevision failed", error);
    return { ok: false, error: "network_error" };
  }
}

/**
 * Suppression (anonymisation) du compte courant : DELETE /api/me. Efface le
 * cookie de session localement dans tous les cas où l'appel réussit — un
 * compte supprimé ne doit plus être considéré comme connecté.
 */
export async function deleteAccount(): Promise<ApiPostResult> {
  const token = await getToken();
  if (!token) {
    return { ok: false, error: "unauthenticated" };
  }

  try {
    const res = await fetch(`${API_URL}/me`, {
      method: "DELETE",
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) {
      const detail = await res
        .json()
        .then((body: unknown) =>
          typeof body === "object" && body && "detail" in body
            ? String((body as { detail: unknown }).detail)
            : null,
        )
        .catch(() => null);
      return { ok: false, error: detail ?? `HTTP ${res.status}` };
    }

    await clearSessionCookie();
    return { ok: true };
  } catch (error) {
    console.error("[auth] deleteAccount failed", error);
    return { ok: false, error: "network_error" };
  }
}
