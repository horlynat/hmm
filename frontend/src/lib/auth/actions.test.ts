// @vitest-environment node
import { beforeEach, describe, expect, it, vi } from "vitest";
import {
  RedirectSignal,
  cookieJar,
  fetchCalls,
  installFetch,
  jsonResponse,
  redirectMock,
  requestHeaders,
  resetServerMocks,
  setFetch,
} from "@/test/serverMocks";

vi.mock("next/headers", async () => {
  const m = await import("@/test/serverMocks");
  return { headers: async () => new Headers(m.requestHeaders), cookies: async () => m.cookieStore };
});
vi.mock("next-intl/server", () => ({ getLocale: async () => "en" }));
vi.mock("@/i18n/navigation", async () => {
  const m = await import("@/test/serverMocks");
  return { redirect: m.redirectMock };
});

import * as actions from "./actions";

const TOKEN = "header.payload.sig";

beforeEach(() => {
  resetServerMocks();
  installFetch();
  cookieJar.set("hmm_token", TOKEN);
  requestHeaders["cf-connecting-ip"] = "203.0.113.50";
  requestHeaders["x-forwarded-for"] = "6.6.6.6, 203.0.113.50, 172.70.0.1";
  requestHeaders["user-agent"] = "Mozilla/5.0 test";
});

describe("M1 — visitor headers on every server-side backend call", () => {
  it.each([
    ["requestPasswordReset", () => actions.requestPasswordReset("a@b.co")],
    ["requestVerificationEmail", () => actions.requestVerificationEmail("a@b.co")],
    ["resendVerificationEmail", () => actions.resendVerificationEmail()],
    ["confirmPasswordReset", () => actions.confirmPasswordReset("tok", "Passw0rd!")],
    ["verifyEmailToken", () => actions.verifyEmailToken("tok")],
    ["login", () => actions.login("a@b.co", "pw")],
    ["changePassword", () => actions.changePassword("old", "New-Passw0rd")],
    ["joinProject", () => actions.joinProject(3)],
  ])("%s forwards the resolved visitor IP and UA (never the spoofed chain)", async (_name, call) => {
    await call();
    expect(fetchCalls).toHaveLength(1);
    expect(fetchCalls[0].init.headers["X-Forwarded-For"]).toBe("203.0.113.50");
    expect(fetchCalls[0].init.headers["User-Agent"]).toBe("Mozilla/5.0 test");
    expect(fetchCalls[0].init.cache).toBe("no-store");
    expect(fetchCalls[0].init.signal).toBeInstanceOf(AbortSignal);
  });
});

describe("F1 — path traversal through Server Action arguments", () => {
  const evilIds: unknown[] = ["1/../../admin", "../users", -1, 0, 1.5, "1", null, { toString: () => "../x" }, 2 ** 40];

  it.each(evilIds)("rejects id %s without calling the backend", async (evil) => {
    const id = evil as number;
    const results = await Promise.all([
      actions.postProjectComment(id, "hello"),
      actions.updateTaskStatus(id, 1, "done"),
      actions.updateTaskStatus(1, id, "done"),
      actions.logTime(id, { minutes: 30 }),
      actions.validateInvoice(id),
      actions.requestInvoiceRevision(id, "trop cher"),
      actions.joinProject(id),
    ]);
    for (const r of results) expect(r).toEqual({ ok: false, error: "invalid_input" });
    expect(fetchCalls).toHaveLength(0);
  });

  it("builds the expected path for valid ids", async () => {
    setFetch(async () => jsonResponse({ id: 9 }));
    await actions.updateTaskStatus(12, 34, "in_progress");
    expect(fetchCalls[0].url).toMatch(/\/me\/projects\/12\/tasks\/34\/status$/);
    expect(JSON.parse(String(fetchCalls[0].init.body))).toEqual({ status: "in_progress" });
  });

  it("rejects a status outside the enum", async () => {
    expect(await actions.updateTaskStatus(1, 2, "archived" as never)).toEqual({ ok: false, error: "invalid_input" });
    expect(fetchCalls).toHaveLength(0);
  });

  it("logTime: strict schema (no extra keys, bounded values, taskId validated)", async () => {
    for (const bad of [
      { minutes: 0 },
      { minutes: -5 },
      { minutes: 30, taskId: "../1" },
      { minutes: 30, spentOn: "2026-01-01T00:00:00Z" },
      { minutes: 30, userId: 1 },
      { minutes: 30, description: "x".repeat(5000) },
    ]) {
      expect(await actions.logTime(1, bad as never)).toEqual({ ok: false, error: "invalid_input" });
    }
    expect(fetchCalls).toHaveLength(0);
    setFetch(async () => jsonResponse({ id: 1 }));
    expect((await actions.logTime(1, { minutes: 90, spentOn: "2026-11-20", taskId: 4 })).ok).toBe(true);
  });

  it("updateProfile: only self-editable fields reach the backend", async () => {
    for (const bad of [{ roles: ["ROLE_ADMIN"] }, { plainPassword: "x" }, { email: "x@y.z" }, { fullName: "x".repeat(300) }]) {
      expect(await actions.updateProfile(bad as never)).toEqual({ ok: false, error: "invalid_input" });
    }
    expect(fetchCalls).toHaveLength(0);
    setFetch(async () => jsonResponse({ id: 1 }));
    expect((await actions.updateProfile({ fullName: "Jane", yearsOfExperience: 3 })).ok).toBe(true);
    expect(JSON.parse(String(fetchCalls[0].init.body))).toEqual({ fullName: "Jane", yearsOfExperience: 3 });
  });
});

describe("F2 — mandatory 2FA enforced on every Server Action", () => {
  const twoFactorRequired = async () => jsonResponse({ code: "2fa_required" }, 403);

  it.each([
    ["updateProfile", () => actions.updateProfile({ fullName: "x" })],
    ["changePassword", () => actions.changePassword("a", "b")],
    ["postProjectComment", () => actions.postProjectComment(1, "x")],
    ["validateInvoice", () => actions.validateInvoice(1)],
    ["deleteAccount", () => actions.deleteAccount()],
    ["getCandidateMessages", () => actions.getCandidateMessages()],
    ["sendCandidateMessage", () => actions.sendCandidateMessage("hello world")],
    ["resendVerificationEmail", () => actions.resendVerificationEmail()],
  ])("%s redirects to the 2FA setup page on 403 2fa_required", async (_name, call) => {
    setFetch(twoFactorRequired);
    await expect(call()).rejects.toBeInstanceOf(RedirectSignal);
    expect(redirectMock).toHaveBeenCalledWith({ href: "/compte/securite", locale: "en" });
  });

  it("an unrelated 403 is a normal error, not a redirect", async () => {
    setFetch(async () => jsonResponse({ detail: "freelance_profile_incomplete" }, 403));
    expect(await actions.joinProject(1)).toEqual({ ok: false, error: "freelance_profile_incomplete" });
    expect(redirectMock).not.toHaveBeenCalled();
  });

  it("2FA setup endpoints never redirect (they are the way out)", async () => {
    setFetch(twoFactorRequired);
    expect(await actions.setupTwoFactor()).toEqual({ ok: false, error: "HTTP 403" });
    expect(redirectMock).not.toHaveBeenCalled();
  });

  it("deleteAccount keeps the session when the backend refuses", async () => {
    setFetch(async () => jsonResponse({ detail: "nope" }, 400));
    expect(await actions.deleteAccount()).toEqual({ ok: false, error: "nope" });
    expect(cookieJar.get("hmm_token")).toBe(TOKEN);
  });
});

describe("F3 — logout revokes the token server-side, then always clears the cookie", () => {
  it("calls POST /api/logout with the bearer token", async () => {
    setFetch(async () => new Response(null, { status: 204 }));
    await actions.logout();
    expect(fetchCalls[0].url).toMatch(/\/logout$/);
    expect(fetchCalls[0].init.method).toBe("POST");
    expect(fetchCalls[0].init.headers.Authorization).toBe(`Bearer ${TOKEN}`);
    expect(cookieJar.has("hmm_token")).toBe(false);
  });

  it.each([
    ["404 (endpoint not deployed yet)", async () => jsonResponse({}, 404)],
    ["401 (already expired)", async () => jsonResponse({}, 401)],
    ["500", async () => jsonResponse({}, 500)],
    ["network error", async () => Promise.reject(new TypeError("fetch failed"))],
  ])("still clears the cookie on %s", async (_label, impl) => {
    setFetch(impl as () => Promise<Response>);
    await expect(actions.logout()).resolves.toBeUndefined();
    expect(cookieJar.has("hmm_token")).toBe(false);
  });

  it("does not call the backend without a session", async () => {
    cookieJar.clear();
    await actions.logout();
    expect(fetchCalls).toHaveLength(0);
  });
});
