// @vitest-environment node
import { describe, expect, it } from "vitest";
import { apiPath, parseResourceId, parseRouteId, resourceIdSchema, supportTicketTokenSchema } from "./path";

describe("apiPath", () => {
  it("encodes each interpolated value as a single segment", () => {
    expect(apiPath`/me/projects/${12}/comments`).toBe("/me/projects/12/comments");
    expect(apiPath`/x/${"a/../../admin?y=1#z"}`).toBe("/x/a%2F..%2F..%2Fadmin%3Fy%3D1%23z");
  });

  it("refuses dot segments that URL parsing would resolve to a parent directory", () => {
    expect(() => apiPath`/support_tickets/${".."}`).toThrow();
    expect(() => apiPath`/support_tickets/${"."}`).toThrow();
    expect(() => apiPath`/support_tickets/${""}`).toThrow();
    // `%2e%2e` (déjà encodé) est ré-encodé (%252e) : plus interprétable comme `..`.
    const url = new URL(`http://api.test/api${apiPath`/support_tickets/${"%2e%2e"}/reply`}`);
    expect(url.pathname).toBe("/api/support_tickets/%252e%252e/reply");
  });

  it("the resulting URL never escapes the intended prefix", () => {
    for (const evil of ["../users", "..%2Fusers", "a/../../b", "%2e%2e/x", "\\..\\x"]) {
      const url = new URL(`http://api.test/api${apiPath`/me/projects/${evil}/comments`}`);
      expect(url.pathname.startsWith("/api/me/projects/")).toBe(true);
      expect(url.pathname.endsWith("/comments")).toBe(true);
    }
  });
});

describe("resource ids", () => {
  it("accepts only positive integers", () => {
    expect(resourceIdSchema.safeParse(1).success).toBe(true);
    for (const bad of [0, -1, 1.5, Number.NaN, Infinity, "1", "1/../2", null, undefined, 2 ** 31, {}]) {
      expect(parseResourceId(bad)).toBeNull();
    }
  });

  it("parses route params strictly", () => {
    expect(parseRouteId("42")).toBe(42);
    for (const bad of ["0", "-1", "1e3", "0x1F", " 4", "4 ", "04", "1.0", "", "99999999999", "1%2F.."]) {
      expect(parseRouteId(bad)).toBeNull();
    }
  });
});

describe("supportTicketTokenSchema", () => {
  it("accepts exactly 64 lowercase hex chars", () => {
    expect(supportTicketTokenSchema.safeParse("a".repeat(64)).success).toBe(true);
    for (const bad of ["A".repeat(64), "a".repeat(63), "a".repeat(65), `${"a".repeat(62)}/.`, "../" + "a".repeat(61), `${"a".repeat(64)}\n`]) {
      expect(supportTicketTokenSchema.safeParse(bad).success).toBe(false);
    }
  });
});
