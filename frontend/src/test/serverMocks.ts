import { vi } from "vitest";

/**
 * Doublures partagées par les tests des modules serveur (Server Actions,
 * Route Handlers) : en-têtes de requête, magasin de cookies, `redirect`
 * next-intl et `fetch`. À utiliser AVEC des `vi.mock(...)` déclarés dans
 * chaque fichier de test (vi.mock est remonté par fichier).
 */
export const requestHeaders: Record<string, string> = {};

export interface CookieWrite {
  name: string;
  value: string;
  options?: Record<string, unknown>;
}

export const cookieJar = new Map<string, string>();
export const cookieWrites: CookieWrite[] = [];

export const cookieStore = {
  get: (name: string) => (cookieJar.has(name) ? { name, value: cookieJar.get(name)! } : undefined),
  set: (name: string, value: string, options?: Record<string, unknown>) => {
    cookieWrites.push({ name, value, options });
    if (options?.maxAge === 0) cookieJar.delete(name);
    else cookieJar.set(name, value);
  },
  delete: (name: string) => {
    cookieWrites.push({ name, value: "", options: { deleted: true } });
    cookieJar.delete(name);
  },
};

export class RedirectSignal extends Error {
  constructor(public readonly target: unknown) {
    super("NEXT_REDIRECT");
  }
}

export const redirectMock = vi.fn((target: unknown) => {
  throw new RedirectSignal(target);
});

export type FetchCall = { url: string; init: RequestInit & { headers: Record<string, string> } };
export const fetchCalls: FetchCall[] = [];
export let fetchImpl: (url: string, init: RequestInit) => Promise<Response> = async () => jsonResponse({});

export function setFetch(impl: (url: string, init: RequestInit) => Promise<Response>) {
  fetchImpl = impl;
}

export function installFetch() {
  vi.stubGlobal("fetch", async (url: string, init: RequestInit) => {
    fetchCalls.push({ url: String(url), init: init as FetchCall["init"] });
    return fetchImpl(String(url), init);
  });
}

export function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } });
}

export function resetServerMocks() {
  for (const k of Object.keys(requestHeaders)) delete requestHeaders[k];
  cookieJar.clear();
  cookieWrites.length = 0;
  fetchCalls.length = 0;
  redirectMock.mockClear();
  fetchImpl = async () => jsonResponse({});
}
