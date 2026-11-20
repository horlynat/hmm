// @vitest-environment node
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { RedirectSignal, cookieJar, cookieWrites, fetchCalls, installFetch, jsonResponse, requestHeaders, resetServerMocks, setFetch } from "@/test/serverMocks";

vi.mock("next/headers", async () => {
  const m = await import("@/test/serverMocks");
  return { headers: async () => new Headers(m.requestHeaders), cookies: async () => m.cookieStore };
});
vi.mock("next-intl/server", () => ({ getLocale: async () => "fr" }));
vi.mock("@/i18n/navigation", async () => {
  const m = await import("@/test/serverMocks");
  return { redirect: m.redirectMock };
});

import { sessionCookieName } from "./config";
import { clearSessionCookie, getCurrentUser, getMyProject, getMyQuote, getToken, setSessionCookie } from "./session";

const jwtIat = (iat: number) => `x.${Buffer.from(JSON.stringify({ iat })).toString("base64url")}.y`;
const jwt = (exp: number) => `x.${Buffer.from(JSON.stringify({ exp })).toString("base64url")}.y`;

beforeEach(() => {
  resetServerMocks();
  installFetch();
  requestHeaders["cf-connecting-ip"] = "203.0.113.9";
});
afterEach(() => vi.unstubAllEnvs());

describe("I5 — __Host- session cookie with soft migration", () => {
  it("uses __Host-hmm_token in production only", () => {
    expect(sessionCookieName("production")).toBe("__Host-hmm_token");
    expect(sessionCookieName("development")).toBe("hmm_token");
    expect(sessionCookieName("test")).toBe("hmm_token");
  });

  it("production: sets __Host- (Secure, Path=/, no Domain) and drops the legacy cookie", async () => {
    vi.stubEnv("NODE_ENV", "production");
    cookieJar.set("hmm_token", "old");
    await setSessionCookie(jwt(Math.floor(Date.now() / 1000) + 600));
    const write = cookieWrites.find((w) => w.name === "__Host-hmm_token")!;
    expect(write.options).toMatchObject({ httpOnly: true, secure: true, path: "/", sameSite: "lax" });
    expect(write.options).not.toHaveProperty("domain");
    expect(cookieJar.has("hmm_token")).toBe(false);
  });

  it("production: still accepts a legacy cookie issued before the cutoff, prefers the new one", async () => {
    vi.stubEnv("NODE_ENV", "production");
    const legacy = jwtIat(Date.UTC(2026, 10, 20) / 1000);
    cookieJar.set("hmm_token", legacy);
    expect(await getToken()).toBe(legacy);
    cookieJar.set("__Host-hmm_token", "fresh");
    expect(await getToken()).toBe("fresh");
  });

  it("production: a legacy cookie minted after the cutoff (planted from a sibling subdomain) is ignored", async () => {
    vi.stubEnv("NODE_ENV", "production");
    for (const planted of [jwtIat(Date.UTC(2026, 11, 2) / 1000), "x.e30.y", "garbage"]) {
      cookieJar.set("hmm_token", planted);
      expect(await getToken()).toBeNull();
    }
  });

  it("production: clearing writes a Secure Path=/ expiry for __Host- and removes the legacy one", async () => {
    vi.stubEnv("NODE_ENV", "production");
    cookieJar.set("__Host-hmm_token", "a");
    cookieJar.set("hmm_token", "b");
    await clearSessionCookie();
    expect(cookieJar.size).toBe(0);
    expect(cookieWrites.find((w) => w.name === "__Host-hmm_token")?.options).toMatchObject({ secure: true, path: "/", maxAge: 0 });
  });
});

describe("session reads", () => {
  beforeEach(() => cookieJar.set("hmm_token", "t"));

  it("getCurrentUser forwards the visitor IP and never redirects (GET /api/me is 2FA-exempt)", async () => {
    setFetch(async () => jsonResponse({ code: "2fa_required" }, 403));
    expect(await getCurrentUser()).toBeNull();
    expect(fetchCalls[0].init.headers["X-Forwarded-For"]).toBe("203.0.113.9");
  });

  it("detail reads redirect to 2FA setup on 403 2fa_required", async () => {
    setFetch(async () => jsonResponse({ code: "2fa_required" }, 403));
    await expect(getMyProject(1)).rejects.toBeInstanceOf(RedirectSignal);
  });

  it("invalid ids never reach the backend", async () => {
    expect(await getMyProject("../../admin" as never)).toBeNull();
    expect(await getMyQuote(-3)).toBeNull();
    expect(fetchCalls).toHaveLength(0);
  });
});
