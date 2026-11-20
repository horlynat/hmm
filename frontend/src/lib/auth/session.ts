import "server-only";

/**
 * Helpers de session côté serveur uniquement (utilisent `next/headers`).
 * Ne jamais importer depuis un Client Component (garanti par `server-only`).
 */

import { cache } from "react";
import { cookies } from "next/headers";
import { LEGACY_SESSION_COOKIE, LEGACY_SESSION_COOKIE_ISSUED_BEFORE, sessionCookieName } from "./config";
import { backendFetch, enforceTwoFactor } from "@/lib/api/backend";
import { apiPath, parseResourceId } from "@/lib/api/path";
import { CURRENCY_COOKIE, DEFAULT_CURRENCY, isCurrency } from "@/lib/currency/config";
import type {
  SessionUser,
  SessionProjectDetail,
  AvailableProject,
  SessionJoinRequest,
  SessionProjectTeam,
  SessionQuoteDetail,
  SessionActivity,
  SessionComment,
  SessionTask,
  SessionTimeTracking,
} from "@/lib/types";

interface JwtPayload {
  exp?: number;
  iat?: number;
  roles?: string[];
  username?: string;
}

/** Décode (sans vérifier la signature — le backend la vérifie à chaque appel) le payload d'un JWT. */
export function decodeJwt(token: string): JwtPayload | null {
  const parts = token.split(".");
  if (parts.length !== 3) return null;
  try {
    const json = Buffer.from(
      parts[1].replace(/-/g, "+").replace(/_/g, "/"),
      "base64",
    ).toString("utf8");
    return JSON.parse(json) as JwtPayload;
  } catch {
    return null;
  }
}

/**
 * Lit le JWT courant depuis le cookie httpOnly, ou null. Migration douce du
 * préfixe `__Host-` (cf. config.ts) : l'ancien cookie `hmm_token` est encore
 * accepté s'il est seul présent ET émis avant la fin de migration (cf.
 * LEGACY_SESSION_COOKIE_ISSUED_BEFORE) — il expire de lui-même avec le JWT
 * qu'il porte et n'est plus jamais réécrit (setSessionCookie le supprime).
 */
export async function getToken(): Promise<string | null> {
  const store = await cookies();
  const name = sessionCookieName();
  const current = store.get(name)?.value;
  if (current) return current;
  if (name === LEGACY_SESSION_COOKIE) return null;

  const legacy = store.get(LEGACY_SESSION_COOKIE)?.value;
  if (!legacy) return null;
  const iat = decodeJwt(legacy)?.iat;
  return typeof iat === "number" && iat < LEGACY_SESSION_COOKIE_ISSUED_BEFORE ? legacy : null;
}

/** Pose le cookie httpOnly de session, avec une expiration alignée sur celle du JWT. */
export async function setSessionCookie(token: string): Promise<void> {
  const store = await cookies();
  const payload = decodeJwt(token);
  const maxAge = payload?.exp
    ? Math.max(0, payload.exp - Math.floor(Date.now() / 1000))
    : 3600;

  const name = sessionCookieName();
  // `__Host-` : Secure + Path=/ + aucun attribut Domain (exigences du préfixe).
  store.set(name, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge,
  });
  if (name !== LEGACY_SESSION_COOKIE) store.delete(LEGACY_SESSION_COOKIE);
}

/** Supprime le cookie de session (déconnexion) — nouveau ET ancien nom. */
export async function clearSessionCookie(): Promise<void> {
  const store = await cookies();
  const name = sessionCookieName();
  if (name.startsWith("__Host-")) {
    // Un cookie `__Host-` n'est remplacé/supprimé par le navigateur que si
    // l'en-tête Set-Cookie respecte lui aussi le préfixe (Secure, Path=/).
    store.set(name, "", { httpOnly: true, secure: true, sameSite: "lax", path: "/", maxAge: 0 });
  } else {
    store.delete(name);
  }
  if (name !== LEGACY_SESSION_COOKIE) store.delete(LEGACY_SESSION_COOKIE);
}

/**
 * GET authentifié générique pour les lectures de l'espace /compte. Renvoie
 * `null` si non authentifié, en cas d'erreur réseau ou de statut non-2xx.
 * Si le backend répond 403 `2fa_required` (compte soumis à la 2FA
 * obligatoire sans 2FA active), redirige vers la mise en place de la 2FA —
 * hors du try/catch, `redirect()` levant NEXT_REDIRECT.
 */
async function authedGet<T>(label: string, path: string): Promise<T | null> {
  const token = await getToken();
  if (!token) return null;

  let res: Response;
  try {
    res = await backendFetch(path, { token });
  } catch (error) {
    console.error(`[auth] GET ${label} failed`, error);
    return null;
  }

  await enforceTwoFactor(res);
  if (!res.ok) return null;

  try {
    return (await res.json()) as T;
  } catch (error) {
    console.error(`[auth] GET ${label} invalid JSON`, error);
    return null;
  }
}

/**
 * Récupère l'utilisateur courant via GET /api/me (Bearer token). Renvoie null
 * si non authentifié ou si le token est invalide/expiré (le backend répond 401).
 * `cache: "no-store"` : jamais de cache HTTP entre requêtes. `React.cache()` :
 * dédoublonne les appels au sein d'un même rendu serveur (le layout `/compte`
 * ET chaque page appellent tous deux `getCurrentUser()` — sans ça, une seule
 * navigation ré-authentifie plusieurs fois sur le firewall API stateless,
 * ce qui rejoue tout effet de bord de l'authentification à chaque fois).
 *
 * Volontairement SANS redirection 2FA : GET /api/me reste accessible à un
 * compte sans 2FA active (le layout /compte s'en sert justement pour afficher
 * l'écran de mise en place) — y rediriger créerait une boucle.
 */
export const getCurrentUser = cache(async (): Promise<SessionUser | null> => {
  const token = await getToken();
  if (!token) return null;

  try {
    const currencyCookie = (await cookies()).get(CURRENCY_COOKIE)?.value;
    const currency = isCurrency(currencyCookie) ? currencyCookie : DEFAULT_CURRENCY;

    const res = await backendFetch(`/me?currency=${encodeURIComponent(currency)}`, { token });
    if (!res.ok) return null;

    return (await res.json()) as SessionUser;
  } catch (error) {
    console.error("[auth] GET /me failed", error);
    return null;
  }
});

/**
 * Détail d'un projet auquel l'utilisateur courant est rattaché. Renvoie null
 * si non authentifié, si le projet n'existe pas, ou si l'utilisateur n'y est
 * pas rattaché (le backend renvoie 404 dans les deux derniers cas — aucune
 * distinction n'est faite pour ne pas révéler l'existence d'un projet privé).
 */
export async function getMyProject(id: number): Promise<SessionProjectDetail | null> {
  const projectId = parseResourceId(id);
  if (projectId === null) return null;
  return authedGet<SessionProjectDetail>("/me/projects/:id", apiPath`/me/projects/${projectId}`);
}

/**
 * Projets "à venir" pas encore affectés à une équipe — l'espace où un
 * freelance au profil complet à 100 % peut se proposer (cf. joinProject()
 * dans actions.ts). Renvoie null si non authentifié, ou si le backend
 * refuse (pas ROLE_EDITOR, profil incomplet) plutôt que de faire
 * planter la page — l'appelant décide de l'affichage dans ce cas.
 */
export async function getAvailableProjects(): Promise<AvailableProject[] | null> {
  const body = await authedGet<{ projects: AvailableProject[] }>("/me/projects/available", "/me/projects/available");
  return body ? body.projects : null;
}

/**
 * Historique complet (tous statuts) des demandes d'auto-association du
 * freelance courant — alimente l'onglet "Mes demandes" du hub
 * "Gestion de projet". `null` si non authentifié/non collaborateur/timeout ;
 * l'appelant décide de l'affichage dans ce cas plutôt que de faire planter la page.
 */
export async function getMyJoinRequests(): Promise<SessionJoinRequest[] | null> {
  const body = await authedGet<{ requests: SessionJoinRequest[] }>("/me/projects/join-requests", "/me/projects/join-requests");
  return body ? body.requests : null;
}

/**
 * Équipe d'un projet (owner + collaborateurs, jamais le client) — sert aussi
 * de garde d'appartenance pour l'espace de travail freelance
 * `/compte/gestion-projet/[id]` : le backend y exclut explicitement le
 * client (contrairement à getMyProject), donc `null` ici doit déclencher un
 * `notFound()`.
 */
export async function getMyProjectTeam(projectId: number): Promise<SessionProjectTeam | null> {
  const id = parseResourceId(projectId);
  if (id === null) return null;
  return authedGet<SessionProjectTeam>("/me/projects/:id/team", apiPath`/me/projects/${id}/team`);
}

/** Tâches d'un projet auquel l'utilisateur courant est rattaché. Renvoie [] si non authentifié, introuvable, ou non-rattaché. */
export async function getMyProjectTasks(projectId: number): Promise<SessionTask[]> {
  const id = parseResourceId(projectId);
  if (id === null) return [];
  const body = await authedGet<{ tasks: SessionTask[] }>("/me/projects/:id/tasks", apiPath`/me/projects/${id}/tasks`);
  return body?.tasks ?? [];
}

/** Suivi du temps d'un projet (toute l'équipe). Renvoie un objet vide si non authentifié, introuvable, ou non-rattaché. */
export async function getMyProjectTimeTracking(projectId: number): Promise<SessionTimeTracking> {
  const empty: SessionTimeTracking = { entries: [], totalMinutes: 0, formattedTotalTime: "0h00", mineMinutes: 0, mineFormattedTime: "0h00" };
  const id = parseResourceId(projectId);
  if (id === null) return empty;
  const body = await authedGet<SessionTimeTracking>("/me/projects/:id/time-entries", apiPath`/me/projects/${id}/time-entries`);
  return body ?? empty;
}

/**
 * Flux d'activité agrégé (historique de projet + messages) sur tous les
 * projets de l'utilisateur courant — GET /api/me/activity. Renvoie des
 * listes vides si non authentifié plutôt que null : le tableau de bord
 * peut afficher ces sections même en cas d'erreur réseau ponctuelle.
 */
export async function getMyActivity(): Promise<SessionActivity> {
  const body = await authedGet<SessionActivity>("/me/activity", "/me/activity");
  return body ?? { history: [], messages: [] };
}

/** Fil de discussion complet d'un projet auquel l'utilisateur courant est rattaché. Renvoie [] si non authentifié, introuvable, ou non-rattaché. */
export async function getProjectComments(projectId: number): Promise<SessionComment[]> {
  const id = parseResourceId(projectId);
  if (id === null) return [];
  const body = await authedGet<{ comments: SessionComment[] }>("/me/projects/:id/comments", apiPath`/me/projects/${id}/comments`);
  return body?.comments ?? [];
}

/** Détail d'un devis appartenant à l'utilisateur courant. Renvoie null si non authentifié, introuvable, ou non-propriétaire. */
export async function getMyQuote(id: number): Promise<SessionQuoteDetail | null> {
  const quoteId = parseResourceId(id);
  if (quoteId === null) return null;
  return authedGet<SessionQuoteDetail>("/me/quotes/:id", apiPath`/me/quotes/${quoteId}`);
}
