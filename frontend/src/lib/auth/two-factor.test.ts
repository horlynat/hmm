// @vitest-environment node
import { describe, expect, it, vi } from "vitest";

vi.mock("server-only", () => ({}));

import { mustSetUpTwoFactor } from "./two-factor";

describe("B2 — 2FA requirement follows the backend policy (single source of truth)", () => {
  it.each([
    ["client, backend requires it", ["ROLE_USER"], true, true],
    ["moderator, backend does not require it", ["ROLE_MODERATOR"], false, false],
    ["manager, backend does not require it", ["ROLE_MANAGER"], false, false],
    ["admin, backend requires it", ["ROLE_ADMIN"], true, true],
  ])("%s", (_label, roles, required, expected) => {
    expect(mustSetUpTwoFactor({ roles, isTwoFactorEnabled: false, isTwoFactorRequired: required })).toBe(expected);
  });

  it("never asks again once 2FA is enabled", () => {
    expect(mustSetUpTwoFactor({ roles: ["ROLE_ADMIN"], isTwoFactorEnabled: true, isTwoFactorRequired: true })).toBe(false);
  });

  it("falls back to the stricter role approximation on an older backend (field absent)", () => {
    expect(mustSetUpTwoFactor({ roles: ["ROLE_USER"], isTwoFactorEnabled: false })).toBe(true);
    expect(mustSetUpTwoFactor({ roles: ["ROLE_MODERATOR"], isTwoFactorEnabled: false })).toBe(true);
    expect(mustSetUpTwoFactor({ roles: ["ROLE_EDITOR"], isTwoFactorEnabled: false })).toBe(false);
  });

  it("ignores a non-boolean value and falls back", () => {
    const user = { roles: ["ROLE_USER"], isTwoFactorEnabled: false, isTwoFactorRequired: "no" as unknown as boolean };
    expect(mustSetUpTwoFactor(user)).toBe(true);
  });
});
