// @vitest-environment node
import { beforeEach, describe, expect, it, vi } from "vitest";

const headerValues: Record<string, string> = {};
vi.mock("next/headers", () => ({
  headers: async () => new Headers(headerValues),
}));

import { createRateLimiter, rateLimit } from "./rate-limit";

describe("createRateLimiter", () => {
  it("allows up to `limit` hits per window then refuses", () => {
    const rl = createRateLimiter();
    const results = Array.from({ length: 4 }, () => rl.hit("k", 3, 1000, 0));
    expect(results).toEqual([true, true, true, false]);
    expect(rl.hit("k", 3, 1000, 1000)).toBe(true); // nouvelle fenêtre
  });

  it("never grows beyond its cap, even with long windows (LRU eviction)", () => {
    const rl = createRateLimiter(100);
    for (let i = 0; i < 10_000; i++) rl.hit(`k${i}`, 5, 60 * 60 * 1000, 0);
    expect(rl.size).toBe(100);
  });

  it("evicts the least recently used key first", () => {
    const rl = createRateLimiter(2);
    rl.hit("a", 1, 10_000, 0);
    rl.hit("b", 1, 10_000, 0);
    rl.hit("a", 1, 10_000, 1); // `a` devient le plus récent (et est refusé)
    rl.hit("c", 1, 10_000, 2); // évince `b`
    expect(rl.hit("a", 1, 10_000, 3)).toBe(false); // compteur de `a` conservé
    expect(rl.hit("b", 1, 10_000, 3)).toBe(true); // `b` repart de zéro
  });

  it("purges expired buckets periodically regardless of size", () => {
    const rl = createRateLimiter();
    for (let i = 0; i < 50; i++) rl.hit(`k${i}`, 5, 1000, 0);
    expect(rl.size).toBe(50);
    rl.hit("trigger", 5, 1000, 60_000);
    expect(rl.size).toBe(1);
  });
});

describe("rateLimit (per visitor IP)", () => {
  beforeEach(() => {
    for (const k of Object.keys(headerValues)) delete headerValues[k];
  });

  it("cannot be bypassed by rotating the first X-Forwarded-For element", async () => {
    const results: boolean[] = [];
    for (let i = 0; i < 5; i++) {
      headerValues["x-forwarded-for"] = `10.0.0.${i}, 198.51.100.77`;
      results.push(await rateLimit("test-xff-bypass", 3, 60_000));
    }
    expect(results).toEqual([true, true, true, false, false]);
  });

  it("isolates distinct visitors (CF-Connecting-IP)", async () => {
    headerValues["cf-connecting-ip"] = "203.0.113.1";
    expect(await rateLimit("test-isolation", 1, 60_000)).toBe(true);
    expect(await rateLimit("test-isolation", 1, 60_000)).toBe(false);
    headerValues["cf-connecting-ip"] = "203.0.113.2";
    expect(await rateLimit("test-isolation", 1, 60_000)).toBe(true);
  });

  it("rotating IPv6 inside the same /64 does not reset the counter", async () => {
    const results: boolean[] = [];
    for (let i = 1; i <= 3; i++) {
      headerValues["cf-connecting-ip"] = `2001:db8:abcd:12::${i}`;
      results.push(await rateLimit("test-v6", 2, 60_000));
    }
    expect(results).toEqual([true, true, false]);
  });
});
