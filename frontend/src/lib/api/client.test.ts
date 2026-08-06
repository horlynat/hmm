import { describe, expect, it } from "vitest";
import { extractCollection } from "./client";

describe("extractCollection", () => {
  it("returns a plain array as-is", () => {
    expect(extractCollection([{ id: 1 }])).toEqual([{ id: 1 }]);
  });

  it("extracts hydra:member from a Hydra collection payload", () => {
    expect(extractCollection({ "hydra:member": [{ id: 1 }] })).toEqual([
      { id: 1 },
    ]);
  });

  it("extracts member from a JSON-LD collection payload", () => {
    expect(extractCollection({ member: [{ id: 2 }] })).toEqual([{ id: 2 }]);
  });

  it("returns an empty array for null/invalid payloads (401 fallback)", () => {
    expect(extractCollection(null)).toEqual([]);
    expect(extractCollection(undefined)).toEqual([]);
    expect(extractCollection({})).toEqual([]);
  });
});
