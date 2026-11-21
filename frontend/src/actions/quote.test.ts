// @vitest-environment node
import { beforeEach, describe, expect, it, vi } from "vitest";
import { cookieJar, fetchCalls, installFetch, jsonResponse, requestHeaders, resetServerMocks, setFetch } from "@/test/serverMocks";

vi.mock("next/headers", async () => {
  const m = await import("@/test/serverMocks");
  return { headers: async () => new Headers(m.requestHeaders), cookies: async () => m.cookieStore };
});

import { submitQuoteRequest } from "./quote";

const TOKEN = "header.payload.sig";
let ip = 0;

const ANSWERS = {
  name: "Jane",
  email: "jane@example.test",
  phone: "",
  type: "site-web",
  categoryDetail: "",
  source: "",
  description: "Un projet de site vitrine.",
  fileName: "",
  budget: "",
  currency: "",
  delai: "",
  canal: "email",
  clarifications: [],
};

beforeEach(() => {
  resetServerMocks();
  installFetch();
  // IP distincte par test : le limiteur en mémoire est partagé par le module.
  requestHeaders["cf-connecting-ip"] = `198.51.100.${++ip}`;
});

describe("B2 — quote submission stays usable for a logged-in client without 2FA set up yet", () => {
  it("sends the Bearer token when a session cookie is present", async () => {
    cookieJar.set("hmm_token", TOKEN);
    setFetch(async () => jsonResponse({}));

    expect(await submitQuoteRequest(ANSWERS)).toEqual({ ok: true });
    expect(fetchCalls).toHaveLength(1);
    expect(fetchCalls[0].init.headers.Authorization).toBe(`Bearer ${TOKEN}`);
  });

  it("never attaches a token when there is no session", async () => {
    setFetch(async () => jsonResponse({}));

    expect(await submitQuoteRequest(ANSWERS)).toEqual({ ok: true });
    expect(fetchCalls).toHaveLength(1);
    expect(fetchCalls[0].init.headers.Authorization).toBeUndefined();
  });

  /**
   * Contre-audit B2 : la 2FA obligatoire couvre maintenant toute l'API.
   * Un client connecté mais sans 2FA recevait donc 403 `2fa_required` sur
   * cet appel authentifié, alors que la même soumission anonyme passait —
   * l'envoi ne doit pas échouer pour autant, seulement se faire sans lien
   * vers le compte.
   */
  it("retries anonymously on a 403 from the authenticated call, and still succeeds", async () => {
    cookieJar.set("hmm_token", TOKEN);
    setFetch(async (_url, init) =>
      init.headers.Authorization ? jsonResponse({ code: "2fa_required" }, 403) : jsonResponse({}),
    );

    expect(await submitQuoteRequest(ANSWERS)).toEqual({ ok: true });
    expect(fetchCalls).toHaveLength(2);
    expect(fetchCalls[0].init.headers.Authorization).toBe(`Bearer ${TOKEN}`);
    expect(fetchCalls[1].init.headers.Authorization).toBeUndefined();
  });

  it.each([
    ["403 with another code", { code: "account_suspended" }],
    ["403 with a detail", { detail: "Demande refusée." }],
    ["403 with an empty body", {}],
  ])("never retries anonymously on a %s (only 2fa_required does)", async (_label, body) => {
    cookieJar.set("hmm_token", TOKEN);
    setFetch(async () => jsonResponse(body, 403));

    const result = await submitQuoteRequest(ANSWERS);
    expect(result.ok).toBe(false);
    expect(fetchCalls).toHaveLength(1);
    expect(fetchCalls[0].init.headers.Authorization).toBe(`Bearer ${TOKEN}`);
  });

  it("does not retry on a 403 unrelated to the token (surfaces the error instead)", async () => {
    setFetch(async () => jsonResponse({ detail: "banni" }, 403));

    expect(await submitQuoteRequest(ANSWERS)).toEqual({ ok: false, error: "banni" });
    expect(fetchCalls).toHaveLength(1);
  });

  it("does not retry on a non-403 error", async () => {
    cookieJar.set("hmm_token", TOKEN);
    setFetch(async () => jsonResponse({ detail: "boom" }, 500));

    expect(await submitQuoteRequest(ANSWERS)).toEqual({ ok: false, error: "boom" });
    expect(fetchCalls).toHaveLength(1);
  });
});
