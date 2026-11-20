import "server-only";
import { getLocale } from "next-intl/server";
import { redirect } from "@/i18n/navigation";
import { API_URL } from "@/lib/auth/config";
import { forwardedVisitorHeaders } from "@/lib/http/visitor";

/**
 * Appels backend authentifiés émis PENDANT une requête (Server Actions,
 * Route Handlers, rendu dynamique de /compte) : Bearer + en-têtes du vrai
 * visiteur + délai maximal + jamais de cache. Les lectures de contenu public
 * mises en cache (build/ISR) passent, elles, par `apiFetch` (client.ts).
 */

export const BACKEND_TIMEOUT_MS = 8000;

export interface BackendRequestInit {
  method?: string;
  token?: string | null;
  /** Sérialisé en JSON (Content-Type posé automatiquement). */
  json?: unknown;
  accept?: string;
  timeoutMs?: number;
}

/** `fetch` vers `${API_URL}${path}` ; lève en cas d'erreur réseau/délai (à l'appelant de capturer). */
export async function backendFetch(path: string, init: BackendRequestInit = {}): Promise<Response> {
  const hasBody = init.json !== undefined;

  return fetch(`${API_URL}${path}`, {
    method: init.method ?? "GET",
    headers: {
      Accept: init.accept ?? "application/json",
      ...(hasBody ? { "Content-Type": "application/json" } : {}),
      ...(init.token ? { Authorization: `Bearer ${init.token}` } : {}),
      ...(await forwardedVisitorHeaders()),
    },
    ...(hasBody ? { body: JSON.stringify(init.json) } : {}),
    cache: "no-store",
    redirect: "manual",
    signal: AbortSignal.timeout(init.timeoutMs ?? BACKEND_TIMEOUT_MS),
  });
}

/** Code d'erreur renvoyé par le backend pour un compte soumis à la 2FA obligatoire sans 2FA active. */
export const TWO_FACTOR_REQUIRED_CODE = "2fa_required";

/**
 * `true` si la réponse est le 403 `{"code":"2fa_required"}` du backend. Lit
 * un CLONE du corps : la réponse d'origine reste consommable par l'appelant.
 */
export async function isTwoFactorRequired(res: Response): Promise<boolean> {
  if (res.status !== 403) return false;
  try {
    const body: unknown = await res.clone().json();
    return typeof body === "object" && body !== null && (body as { code?: unknown }).code === TWO_FACTOR_REQUIRED_CODE;
  } catch {
    return false;
  }
}

/**
 * Redirige vers l'écran de mise en place de la 2FA (la page /compte/securite,
 * où CompteLayout affiche TwoFactorSetupGate tant que la 2FA n'est pas
 * active). `redirect()` lève NEXT_REDIRECT : à appeler HORS de tout
 * try/catch, sinon l'exception serait avalée.
 */
export async function redirectToTwoFactorSetup(): Promise<never> {
  const locale = await getLocale();
  redirect({ href: "/compte/securite", locale });
  // `redirect` est typé `never` côté next-intl, mais on garde une garde explicite.
  throw new Error("unreachable");
}

/** Raccourci : redirige si `res` est le 403 2FA obligatoire, sinon ne fait rien. */
export async function enforceTwoFactor(res: Response): Promise<void> {
  if (await isTwoFactorRequired(res)) {
    await redirectToTwoFactorSetup();
  }
}

/** Extrait le `detail` lisible d'une erreur API Platform/contrôleur, ou `null`. */
export async function extractDetail(res: Response): Promise<string | null> {
  return res
    .json()
    .then((body: unknown) =>
      typeof body === "object" && body && "detail" in body ? String((body as { detail: unknown }).detail) : null,
    )
    .catch(() => null);
}
