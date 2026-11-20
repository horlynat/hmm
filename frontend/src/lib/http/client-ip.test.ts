// @vitest-environment node
import { describe, expect, it } from "vitest";
import { normalizeIp, rateLimitKeyForIp, resolveClientIp, trustedProxyHopsFromEnv } from "./client-ip";
import { visitorHeadersFrom } from "./visitor";

const h = (values: Record<string, string>) => new Headers(values);

describe("resolveClientIp", () => {
  it("prefers CF-Connecting-IP (set by Cloudflare, not forgeable behind cloudflare-only)", () => {
    expect(
      resolveClientIp(h({ "cf-connecting-ip": "203.0.113.7", "x-forwarded-for": "6.6.6.6, 203.0.113.7, 172.70.1.1" })),
    ).toBe("203.0.113.7");
  });

  it("never uses the client-controlled first X-Forwarded-For element", () => {
    // Attaque : le client préfixe une fausse IP ; Traefik ajoute le vrai pair à droite.
    expect(resolveClientIp(h({ "x-forwarded-for": "1.2.3.4, 198.51.100.9" }))).toBe("198.51.100.9");
    expect(resolveClientIp(h({ "x-forwarded-for": "1.2.3.4, 5.6.7.8, 198.51.100.9" }), { trustedProxyHops: 2 })).toBe("5.6.7.8");
  });

  it("rotating the spoofed prefix yields the same key", () => {
    const keys = new Set(
      ["1.1.1.1", "2.2.2.2", "3.3.3.3, 4.4.4.4"].map((spoof) =>
        resolveClientIp(h({ "x-forwarded-for": `${spoof}, 198.51.100.9` })),
      ),
    );
    expect([...keys]).toEqual(["198.51.100.9"]);
  });

  it("rejects garbage and falls back to null", () => {
    expect(resolveClientIp(h({ "cf-connecting-ip": "not-an-ip" }))).toBeNull();
    expect(resolveClientIp(h({ "x-forwarded-for": "evil<script>" }))).toBeNull();
    expect(resolveClientIp(h({ "x-real-ip": "9.9.9.9" }))).toBeNull(); // X-Real-IP jamais lu
    expect(resolveClientIp(h({}))).toBeNull();
  });

  it("ignores an invalid CF-Connecting-IP and uses the trusted XFF hop instead", () => {
    expect(resolveClientIp(h({ "cf-connecting-ip": "x", "x-forwarded-for": "1.1.1.1, 198.51.100.9" }))).toBe("198.51.100.9");
  });

  it("bounds huge headers (rightmost part only)", () => {
    const huge = `${"9.9.9.9, ".repeat(5000)}198.51.100.9`;
    expect(resolveClientIp(h({ "x-forwarded-for": huge }))).toBe("198.51.100.9");
  });

  it("reads TRUSTED_PROXY_HOPS defensively", () => {
    expect(trustedProxyHopsFromEnv({})).toBe(1);
    expect(trustedProxyHopsFromEnv({ TRUSTED_PROXY_HOPS: "2" })).toBe(2);
    expect(trustedProxyHopsFromEnv({ TRUSTED_PROXY_HOPS: "0" })).toBe(1);
    expect(trustedProxyHopsFromEnv({ TRUSTED_PROXY_HOPS: "abc" })).toBe(1);
  });
});

describe("normalizeIp / rateLimitKeyForIp", () => {
  it("normalizes IPv4-mapped IPv6 and brackets", () => {
    expect(normalizeIp("::ffff:203.0.113.7")).toBe("203.0.113.7");
    expect(normalizeIp("[2001:DB8::1]")).toBe("2001:db8::1");
  });

  it("groups IPv6 addresses by /64", () => {
    const a = rateLimitKeyForIp("2001:db8:1:2:aaaa:bbbb:cccc:dddd");
    const b = rateLimitKeyForIp("2001:db8:1:2::1");
    expect(a).toBe("2001:db8:1:2::/64");
    expect(b).toBe(a);
    expect(rateLimitKeyForIp("2001:db8:1:3::1")).not.toBe(a);
    expect(rateLimitKeyForIp("::1")).toBe("0:0:0:0::/64");
  });

  it("keeps IPv4 as-is, unknown for null, always bounded", () => {
    expect(rateLimitKeyForIp("203.0.113.7")).toBe("203.0.113.7");
    expect(rateLimitKeyForIp(null)).toBe("unknown");
    expect(rateLimitKeyForIp("x".repeat(500)).length).toBeLessThanOrEqual(45);
  });
});

describe("visitorHeadersFrom", () => {
  it("forwards only the resolved IP, never the raw spoofable chain", () => {
    const headers = visitorHeadersFrom(
      h({ "x-forwarded-for": "6.6.6.6, 203.0.113.7, 172.70.1.1", "cf-connecting-ip": "203.0.113.7", "user-agent": "UA" }),
    );
    expect(headers).toEqual({ "X-Forwarded-For": "203.0.113.7", "User-Agent": "UA" });
  });

  it("omits X-Forwarded-For when no trustworthy IP is known, and bounds the UA", () => {
    const headers = visitorHeadersFrom(h({ "user-agent": "A".repeat(5000) }));
    expect(headers["X-Forwarded-For"]).toBeUndefined();
    expect(headers["User-Agent"]).toHaveLength(255);
  });
});
