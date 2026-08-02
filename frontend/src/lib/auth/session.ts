/**
 * Helpers de session côté serveur uniquement (utilisent `next/headers`).
 * Ne jamais importer depuis un Client Component.
 */

import { cache } from "react";
import { cookies } from "next/headers";
import { API_URL, SESSION_COOKIE } from "./config";
import type {
  SessionUser,
  SessionProjectDetail,
  SessionQuoteDetail,
  SessionActivity,
  SessionComment,
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
    const res = await fetch(`${API_URL}/me`, {
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
