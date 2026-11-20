// @vitest-environment node
import { beforeEach, describe, expect, it, vi } from "vitest";
import { NextRequest } from "next/server";
import { cookieJar, fetchCalls, installFetch, jsonResponse, resetServerMocks, setFetch } from "@/test/serverMocks";

vi.mock("next/headers", async () => {
  const m = await import("@/test/serverMocks");
  return { headers: async () => new Headers(m.requestHeaders), cookies: async () => m.cookieStore };
});
vi.mock("next-intl/server", () => ({ getLocale: async () => "fr" }));

import { GET } from "./route";

const req = (q = "") => new NextRequest(`https://horlynat.com/api/me/export${q}`);
const user = (extra: Record<string, unknown>) => ({ id: 1, email: "a@b.c", roles: ["ROLE_USER"], isTwoFactorEnabled: true, ...extra });

beforeEach(() => {
  resetServerMocks();
  installFetch();
  cookieJar.set("hmm_token", "t");
});

describe("F2 / I6 — /api/me/export", () => {
  it("401 without a session, never cached", async () => {
    cookieJar.clear();
    const res = await GET(req());
    expect(res.status).toBe(401);
    expect(res.headers.get("cache-control")).toContain("no-store");
  });

  it("serves the export as an uncached attachment, with a timeout on the backend call", async () => {
    setFetch(async () => jsonResponse(user({})));
    const res = await GET(req());
    expect(res.status).toBe(200);
    expect(res.headers.get("cache-control")).toContain("no-store");
    expect(res.headers.get("content-disposition")).toContain("attachment");
    expect(fetchCalls[0].init.signal).toBeInstanceOf(AbortSignal);
  });

  it("refuses the export when mandatory 2FA is not active, even though GET /api/me answers 200", async () => {
    setFetch(async () => jsonResponse(user({ isTwoFactorEnabled: false })));
    const res = await GET(req("?locale=en"));
    expect(res.status).toBe(303);
    expect(res.headers.get("location")).toBe("https://horlynat.com/en/account/security");
    expect(res.headers.get("cache-control")).toContain("no-store");
  });

  it("honours a backend 403 2fa_required too", async () => {
    setFetch(async () => jsonResponse({ code: "2fa_required" }, 403));
    const res = await GET(req("?locale=fr"));
    expect(res.status).toBe(303);
    expect(res.headers.get("location")).toBe("https://horlynat.com/fr/compte/securite");
  });

  it("ROLE_EDITOR is exempt (same rule as the /compte layout)", async () => {
    setFetch(async () => jsonResponse(user({ roles: ["ROLE_USER", "ROLE_EDITOR"], isTwoFactorEnabled: false })));
    expect((await GET(req())).status).toBe(200);
  });

  it("an unknown/hostile locale cannot steer the redirect off-site", async () => {
    setFetch(async () => jsonResponse(user({ isTwoFactorEnabled: false })));
    for (const evil of ["//evil.test", "https://evil.test", "../x"]) {
      const res = await GET(req(`?locale=${encodeURIComponent(evil)}`));
      expect(new URL(res.headers.get("location")!).origin).toBe("https://horlynat.com");
    }
  });

  it("backend outage → 502, no data leaked, still no-store", async () => {
    setFetch(async () => Promise.reject(new Error("timeout")));
    const res = await GET(req());
    expect(res.status).toBe(502);
    expect(res.headers.get("cache-control")).toContain("no-store");
  });
});
