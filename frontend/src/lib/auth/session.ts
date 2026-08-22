/**
 * Helpers de session côté serveur uniquement (utilisent `next/headers`).
 * Ne jamais importer depuis un Client Component.
 */

import { cache } from "react";
import { cookies } from "next/headers";
import { API_URL, SESSION_COOKIE } from "./config";
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

/** Lit le JWT courant depuis le cookie httpOnly, ou null. */
export async function getToken(): Promise<string | null> {
  const store = await cookies();
  return store.get(SESSION_COOKIE)?.value ?? null;
}

/** Pose le cookie httpOnly de session, avec une expiration alignée sur celle du JWT. */
export async function setSessionCookie(token: string): Promise<void> {
  const store = await cookies();
  const payload = decodeJwt(token);
  const maxAge = payload?.exp
    ? Math.max(0, payload.exp - Math.floor(Date.now() / 1000))
    : 3600;

  store.set(SESSION_COOKIE, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge,
  });
}

/** Supprime le cookie de session (déconnexion). */
export async function clearSessionCookie(): Promise<void> {
  const store = await cookies();
  store.delete(SESSION_COOKIE);
}

/**
 * Récupère l'utilisateur courant via GET /api/me (Bearer token). Renvoie null
 * si non authentifié ou si le token est invalide/expiré (le backend répond 401).
 * `cache: "no-store"` : jamais de cache HTTP entre requêtes. `React.cache()` :
 * dédoublonne les appels au sein d'un même rendu serveur (le layout `/compte`
 * ET chaque page appellent tous deux `getCurrentUser()` — sans ça, une seule
 * navigation ré-authentifie plusieurs fois sur le firewall API stateless,
 * ce qui rejoue tout effet de bord de l'authentification à chaque fois).
 */
export const getCurrentUser = cache(async (): Promise<SessionUser | null> => {
  const token = await getToken();
  if (!token) return null;

  try {
    const currencyCookie = (await cookies()).get(CURRENCY_COOKIE)?.value;
    const currency = isCurrency(currencyCookie) ? currencyCookie : DEFAULT_CURRENCY;

    const res = await fetch(`${API_URL}/me?currency=${currency}`, {
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

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
  const token = await getToken();
  if (!token) return null;

  try {
    const res = await fetch(`${API_URL}/me/projects/${id}`, {
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) return null;

    return (await res.json()) as SessionProjectDetail;
  } catch (error) {
    console.error("[auth] GET /me/projects/:id failed", error);
    return null;
  }
}

/**
 * Projets "à venir" pas encore affectés à une équipe — l'espace où un
 * freelance au profil complet à 100 % peut se proposer (cf. joinProject()
 * dans actions.ts). Renvoie null si non authentifié, ou si le backend
 * refuse (pas ROLE_EDITOR, profil incomplet) plutôt que de faire
 * planter la page — l'appelant décide de l'affichage dans ce cas.
 */
export async function getAvailableProjects(): Promise<AvailableProject[] | null> {
  const token = await getToken();
  if (!token) return null;

  try {
    const res = await fetch(`${API_URL}/me/projects/available`, {
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) return null;

    const body = (await res.json()) as { projects: AvailableProject[] };
    return body.projects;
  } catch (error) {
    console.error("[auth] GET /me/projects/available failed", error);
    return null;
  }
}

/**
 * Historique complet (tous statuts) des demandes d'auto-association du
 * freelance courant — alimente l'onglet "Mes demandes" du hub
 * "Gestion de projet". `null` si non authentifié/non collaborateur/timeout ;
 * l'appelant décide de l'affichage dans ce cas plutôt que de faire planter la page.
 */
export async function getMyJoinRequests(): Promise<SessionJoinRequest[] | null> {
  const token = await getToken();
  if (!token) return null;

  try {
    const res = await fetch(`${API_URL}/me/projects/join-requests`, {
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) return null;

    const body = (await res.json()) as { requests: SessionJoinRequest[] };
    return body.requests;
  } catch (error) {
    console.error("[auth] GET /me/projects/join-requests failed", error);
    return null;
  }
}

/**
 * Équipe d'un projet (owner + collaborateurs, jamais le client) — sert aussi
 * de garde d'appartenance pour l'espace de travail freelance
 * `/compte/gestion-projet/[id]` : le backend y exclut explicitement le
 * client (contrairement à getMyProject), donc `null` ici doit déclencher un
 * `notFound()`.
 */
export async function getMyProjectTeam(projectId: number): Promise<SessionProjectTeam | null> {
  const token = await getToken();
  if (!token) return null;

  try {
    const res = await fetch(`${API_URL}/me/projects/${projectId}/team`, {
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) return null;

    return (await res.json()) as SessionProjectTeam;
  } catch (error) {
    console.error("[auth] GET /me/projects/:id/team failed", error);
    return null;
  }
}

/** Tâches d'un projet auquel l'utilisateur courant est rattaché. Renvoie [] si non authentifié, introuvable, ou non-rattaché. */
export async function getMyProjectTasks(projectId: number): Promise<SessionTask[]> {
  const token = await getToken();
  if (!token) return [];

  try {
    const res = await fetch(`${API_URL}/me/projects/${projectId}/tasks`, {
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) return [];

    const body = (await res.json()) as { tasks: SessionTask[] };
    return body.tasks;
  } catch (error) {
    console.error("[auth] GET /me/projects/:id/tasks failed", error);
    return [];
  }
}

/** Suivi du temps d'un projet (toute l'équipe). Renvoie un objet vide si non authentifié, introuvable, ou non-rattaché. */
export async function getMyProjectTimeTracking(projectId: number): Promise<SessionTimeTracking> {
  const empty: SessionTimeTracking = { entries: [], totalMinutes: 0, formattedTotalTime: "0h00", mineMinutes: 0, mineFormattedTime: "0h00" };
  const token = await getToken();
  if (!token) return empty;

  try {
    const res = await fetch(`${API_URL}/me/projects/${projectId}/time-entries`, {
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) return empty;

    return (await res.json()) as SessionTimeTracking;
  } catch (error) {
    console.error("[auth] GET /me/projects/:id/time-entries failed", error);
    return empty;
  }
}

/**
 * Flux d'activité agrégé (historique de projet + messages) sur tous les
 * projets de l'utilisateur courant — GET /api/me/activity. Renvoie des
 * listes vides si non authentifié plutôt que null : le tableau de bord
 * peut afficher ces sections même en cas d'erreur réseau ponctuelle.
 */
export async function getMyActivity(): Promise<SessionActivity> {
  const empty: SessionActivity = { history: [], messages: [] };
  const token = await getToken();
  if (!token) return empty;

  try {
    const res = await fetch(`${API_URL}/me/activity`, {
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) return empty;

    return (await res.json()) as SessionActivity;
  } catch (error) {
    console.error("[auth] GET /me/activity failed", error);
    return empty;
  }
}

/** Fil de discussion complet d'un projet auquel l'utilisateur courant est rattaché. Renvoie [] si non authentifié, introuvable, ou non-rattaché. */
export async function getProjectComments(projectId: number): Promise<SessionComment[]> {
  const token = await getToken();
  if (!token) return [];

  try {
    const res = await fetch(`${API_URL}/me/projects/${projectId}/comments`, {
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) return [];

    const body = (await res.json()) as { comments: SessionComment[] };
    return body.comments;
  } catch (error) {
    console.error("[auth] GET /me/projects/:id/comments failed", error);
    return [];
  }
}

/** Détail d'un devis appartenant à l'utilisateur courant. Renvoie null si non authentifié, introuvable, ou non-propriétaire. */
export async function getMyQuote(id: number): Promise<SessionQuoteDetail | null> {
  const token = await getToken();
  if (!token) return null;

  try {
    const res = await fetch(`${API_URL}/me/quotes/${id}`, {
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) return null;

    return (await res.json()) as SessionQuoteDetail;
  } catch (error) {
    console.error("[auth] GET /me/quotes/:id failed", error);
    return null;
  }
}
