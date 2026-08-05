"use server";

import { API_URL } from "./config";
import {
  clearSessionCookie,
  getCurrentUser,
  getToken,
  setSessionCookie,
} from "./session";
import type { ApiPostResult } from "@/lib/api/client";
import type { ProfileUpdatePayload, SessionUser, SessionComment, SessionInvoice } from "@/lib/types";

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
