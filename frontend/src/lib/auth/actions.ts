"use server";

import { API_URL } from "./config";
import {
  clearSessionCookie,
  getCurrentUser,
  getToken,
  setSessionCookie,
} from "./session";
import type { ApiPostResult } from "@/lib/api/client";
import type { ProfileUpdatePayload, SessionUser } from "@/lib/types";

/**
 * Connexion : POST /api/login_check (lexik JWT). En cas de succès, le token est
 * posé dans un cookie httpOnly. Le mot de passe ne transite que côté serveur.
 */
export async function login(
  email: string,
  password: string,
): Promise<ApiPostResult> {
  try {
    const res = await fetch(`${API_URL}/login_check`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ email, password }),
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) {
      // lexik renvoie 401 { message: "Invalid credentials." } ; on ne fuite pas
      // le détail (email inexistant vs mauvais mot de passe) à l'utilisateur.
      return { ok: false, error: res.status === 401 ? "invalid_credentials" : `HTTP ${res.status}` };
    }

    const body = (await res.json()) as { token?: string };
    if (!body.token) {
      return { ok: false, error: "no_token" };
    }

    await setSessionCookie(body.token);
    return { ok: true };
  } catch (error) {
    console.error("[auth] login failed", error);
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
