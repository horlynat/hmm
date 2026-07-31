/**
 * Helpers de session côté serveur uniquement (utilisent `next/headers`).
 * Ne jamais importer depuis un Client Component.
 */

import { cookies } from "next/headers";
import { API_URL, SESSION_COOKIE } from "./config";
import type { SessionUser } from "@/lib/types";

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
 * `cache: "no-store"` : la session ne doit jamais être mise en cache.
 */
export async function getCurrentUser(): Promise<SessionUser | null> {
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
}
