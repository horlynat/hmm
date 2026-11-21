// @vitest-environment node
import { describe, expect, it } from "vitest";
import { NextRequest } from "next/server";
// Fonction interne de Next.js qui compile `config.matcher` exactement comme au build.
import { getMiddlewareMatchers } from "next/dist/build/analysis/get-page-static-info";
import { config, proxy } from "./proxy";
import { needsStrictCsp } from "@/lib/csp";

function run(path: string, headers: Record<string, string> = {}) {
  return proxy(new NextRequest(`https://horlynat.com${path}`, { headers: { "accept-language": "fr", ...headers } }));
}

/** Valeur d'un request header surchargé par le proxy (mécanique x-middleware-request-*). */
function overriddenRequestHeader(res: Response, name: string): string | null {
  return res.headers.get(`x-middleware-request-${name.toLowerCase()}`);
}

describe("F4 — strict nonce CSP on /contact and the whole account area", () => {
  it.each(["/fr/contact", "/en/contact", "/fr/compte", "/fr/compte/", "/fr/compte/projets/12", "/en/account", "/en/account/projects/12", "/fr/compte/x.y"])(
    "%s gets a nonce-based script-src without unsafe-inline",
    (path) => {
      const res = run(path);
      const csp = res.headers.get("content-security-policy") ?? "";
      expect(csp).toMatch(/script-src 'self' 'nonce-[A-Za-z0-9+/=]+' 'strict-dynamic'/);
      expect(csp.split(";").find((d) => d.trim().startsWith("script-src"))).not.toContain("unsafe-inline");
    },
  );

  it("passes the nonce and CSP to the render through request headers, also on next-intl rewrites (/en/account)", () => {
    for (const path of ["/fr/compte/profil", "/en/account/profile"]) {
      const res = run(path);
      const nonce = overriddenRequestHeader(res, "x-nonce");
      expect(nonce).toBeTruthy();
      expect(overriddenRequestHeader(res, "content-security-policy")).toContain(`'nonce-${nonce}'`);
      expect(res.headers.get("content-security-policy")).toContain(`'nonce-${nonce}'`);
      expect(res.headers.get("x-middleware-override-headers")).toContain("x-nonce");
    }
    // /en/account est bien réécrit vers la route interne /en/compte.
    expect(run("/en/account/profile").headers.get("x-middleware-rewrite")).toContain("/en/compte/profil");
  });

  it("a fresh nonce per request", () => {
    const a = overriddenRequestHeader(run("/fr/compte"), "x-nonce");
    const b = overriddenRequestHeader(run("/fr/compte"), "x-nonce");
    expect(a).not.toBe(b);
  });

  it("a client-supplied x-nonce / CSP request header never reaches the render", () => {
    const strict = run("/fr/compte", { "x-nonce": "attacker", "content-security-policy": "script-src *" });
    expect(overriddenRequestHeader(strict, "x-nonce")).not.toBe("attacker");
    expect(overriddenRequestHeader(strict, "content-security-policy")).not.toContain("script-src *");

    const relaxed = run("/fr/blog", { "x-nonce": "attacker", "content-security-policy": "script-src *" });
    expect(overriddenRequestHeader(relaxed, "x-nonce")).toBeNull();
    expect(overriddenRequestHeader(relaxed, "content-security-policy")).toBeNull();
  });

  it("other pages keep the SSG-compatible default CSP", () => {
    for (const path of ["/fr", "/fr/blog/mon-article", "/en/projects/x", "/fr/comptes-rendus", "/fr/contactez-nous"]) {
      expect(needsStrictCsp(path)).toBe(false);
      expect(run(path).headers.get("content-security-policy")).toContain("'unsafe-inline'");
    }
  });
});

describe("F4 — proxy matcher only skips real static assets", () => {
  const [matcher] = getMiddlewareMatchers(config.matcher, { i18n: undefined } as never);
  const re = new RegExp(matcher.regexp);

  it.each(["/fr/blog/nextjs-16.4", "/fr/realisations/site.v2", "/fr/compte/x.y", "/fr", "/", "/fr/compte"])(
    "runs on %s",
    (path) => expect(re.test(path)).toBe(true),
  );

  it.each([
    "/icon.svg",
    "/robots.txt",
    "/sitemap.xml",
    "/manifest.webmanifest",
    "/cv-horlynat-mampassi-mbama.pdf",
    "/images/photo.jpg",
    "/_next/static/chunks/main.js",
    "/_next/image",
    "/api/me/export",
    "/api",
    "/_vercel/insights",
  ])("skips %s", (path) => expect(re.test(path)).toBe(false));
});
