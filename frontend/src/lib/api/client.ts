/**
 * Client API serveur uniquement — jamais importé depuis un Client Component.
 *
 * `apiFetch`/`apiPost` gèrent l'échec en douceur (null / objet d'erreur)
 * plutôt que de faire planter la page, pour tolérer un backend indisponible
 * ou une ressource momentanément non exposée publiquement.
 */

const API_URL = process.env.API_URL ?? "http://127.0.0.1:8000/api";

/**
 * Délai maximal d'un appel API. Sans lui, un backend lent ou injoignable
 * (paquets droppés) bloquerait indéfiniment le rendu — et le build (sitemap,
 * pages statiques). En cas de dépassement, l'appel échoue proprement (null /
 * erreur) comme n'importe quelle autre panne API.
 */
const API_TIMEOUT_MS = 8000;

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
      signal: AbortSignal.timeout(API_TIMEOUT_MS),
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

/**
 * Sélectionne le champ dans la langue courante parmi une paire FR (toujours
 * renseignée) / EN (optionnelle, ajoutée par la migration Version20260731065849).
 * Retombe sur la valeur FR si la traduction anglaise est absente — même
 * logique côté backend (cf. commentaires des champs `*En` sur les entités).
 */
export function pickLocalized(fr: string, en: string | null | undefined, locale: string): string;
export function pickLocalized(
  fr: string | null,
  en: string | null | undefined,
  locale: string,
): string | null;
export function pickLocalized(
  fr: string | null,
  en: string | null | undefined,
  locale: string,
): string | null {
  return locale === "en" && en ? en : fr;
}

/** Variante liste : retombe sur la version FR si la liste EN est absente ou vide. */
export function pickLocalizedList<T>(fr: T[], en: T[] | null | undefined, locale: string): T[] {
  return locale === "en" && en && en.length > 0 ? en : fr;
}

/**
 * API Platform renvoie un "detail" lisible sur les erreurs de validation
 * (ex: "email: Il existe déjà un compte avec cet email.") — utile pour les
 * formulaires qui veulent afficher un message précis plutôt que générique
 * (ex: inscription collaborateur). Partagé entre apiPost et apiPostWithData.
 */
async function extractErrorDetail(res: Response): Promise<string | null> {
  return res
    .json()
    .then((body: unknown) =>
      typeof body === "object" && body && "detail" in body
        ? String((body as { detail: unknown }).detail)
        : null,
    )
    .catch(() => null);
}

export type ApiPostResult = { ok: true } | { ok: false; error: string };

export async function apiPost<T extends object>(
  path: string,
  body: T,
  options: { token?: string } = {},
): Promise<ApiPostResult> {
  try {
    const res = await fetch(`${API_URL}${path}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/ld+json",
        Accept: "application/ld+json",
        ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}),
      },
      body: JSON.stringify(body),
      signal: AbortSignal.timeout(API_TIMEOUT_MS),
    });

    if (!res.ok) {
      console.error(`[api] POST ${path} -> ${res.status} ${res.statusText}`);
      const detail = await extractErrorDetail(res);
      return { ok: false, error: detail ?? `HTTP ${res.status}` };
    }

    return { ok: true };
  } catch (error) {
    console.error(`[api] POST ${path} failed`, error);
    return { ok: false, error: "network_error" };
  }
}

export type ApiPostDataResult<T> = { ok: true; data: T } | { ok: false; error: string };

/**
 * Variante de apiPost qui renvoie le corps de la réponse sur succès — apiPost
 * l'ignore volontairement (`{ok:true}` seul), ce qui suffit pour une création
 * "fire and forget" mais pas quand l'appelant a besoin de la donnée générée
 * par le backend (ex: questions de qualification IA). `timeoutMs` permet de
 * dépasser le délai par défaut pour un appel qui invoque un LLM côté backend.
 */
export async function apiPostWithData<T>(
  path: string,
  body: object,
  options: { token?: string; timeoutMs?: number } = {},
): Promise<ApiPostDataResult<T>> {
  try {
    const res = await fetch(`${API_URL}${path}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/ld+json",
        Accept: "application/ld+json",
        ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}),
      },
      body: JSON.stringify(body),
      signal: AbortSignal.timeout(options.timeoutMs ?? API_TIMEOUT_MS),
    });

    if (!res.ok) {
      console.error(`[api] POST ${path} -> ${res.status} ${res.statusText}`);
      const detail = await extractErrorDetail(res);
      return { ok: false, error: detail ?? `HTTP ${res.status}` };
    }

    return { ok: true, data: (await res.json()) as T };
  } catch (error) {
    console.error(`[api] POST ${path} failed`, error);
    return { ok: false, error: "network_error" };
  }
}
