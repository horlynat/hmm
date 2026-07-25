/**
 * Client API serveur uniquement — jamais importé depuis un Client Component.
 *
 * IMPORTANT : à l'heure actuelle, `backend/config/packages/security.yaml`
 * contient `access_control: [{ path: ^/api, roles: IS_AUTHENTICATED_FULLY }]`,
 * qui s'applique avant les opérations publiques déclarées sur chaque
 * `*ApiResource`. Tant que ce n'est pas corrigé côté backend, tout appel ici
 * renverra 401. apiFetch/apiPost gèrent donc l'échec en douceur (null / objet
 * d'erreur) plutôt que de faire planter la page — voir le plan pour le détail.
 */

const API_URL = process.env.API_URL ?? "http://127.0.0.1:8000/api";

interface FetchOptions {
  tags?: string[];
  revalidate?: number | false;
}

export async function apiFetch<T>(
  path: string,
  options: FetchOptions = {},
): Promise<T | null> {
  try {
    const res = await fetch(`${API_URL}${path}`, {
      headers: { Accept: "application/ld+json" },
      next: {
        tags: options.tags,
        revalidate: options.revalidate ?? 3600,
      },
    });

    if (!res.ok) {
      console.error(`[api] GET ${path} -> ${res.status} ${res.statusText}`);
      return null;
    }

    return (await res.json()) as T;
  } catch (error) {
    console.error(`[api] GET ${path} failed`, error);
    return null;
  }
}

/** Normalise une réponse de collection API Platform (Hydra `hydra:member`, JSON-LD `member`, ou tableau brut). */
export function extractCollection<T>(payload: unknown): T[] {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload as T[];
  if (typeof payload === "object") {
    const record = payload as Record<string, unknown>;
    const member = record["hydra:member"] ?? record["member"];
    if (Array.isArray(member)) return member as T[];
  }
  return [];
}

export type ApiPostResult = { ok: true } | { ok: false; error: string };

export async function apiPost<T extends object>(
  path: string,
  body: T,
): Promise<ApiPostResult> {
  try {
    const res = await fetch(`${API_URL}${path}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/ld+json",
        Accept: "application/ld+json",
      },
      body: JSON.stringify(body),
    });

    if (!res.ok) {
      console.error(`[api] POST ${path} -> ${res.status} ${res.statusText}`);
      return { ok: false, error: `HTTP ${res.status}` };
    }

    return { ok: true };
  } catch (error) {
    console.error(`[api] POST ${path} failed`, error);
    return { ok: false, error: "network_error" };
  }
}
