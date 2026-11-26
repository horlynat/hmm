import { describe, expect, it } from "vitest";
import { isSvgFile } from "./media";

describe("isSvgFile", () => {
  it("reconnaît un SVG, y compris compressé, avec requête ou ancre, insensible à la casse", () => {
    expect(isSvgFile("/uploads/projects/cover-96cd05a09b12.svg")).toBe(true);
    expect(isSvgFile("/uploads/projects/cover.SVG")).toBe(true);
    expect(isSvgFile("/uploads/projects/cover.svgz")).toBe(true);
    expect(isSvgFile("/uploads/projects/cover.svg?v=2")).toBe(true);
    expect(isSvgFile("/uploads/projects/cover.svg#frag")).toBe(true);
  });

  it("ne reconnaît pas une image matricielle, même avec « svg » dans le nom", () => {
    expect(isSvgFile("/uploads/projects/cover.png")).toBe(false);
    expect(isSvgFile("/uploads/projects/cover.webp")).toBe(false);
    expect(isSvgFile("/uploads/projects/cover.jpg")).toBe(false);
    expect(isSvgFile("/uploads/projects/my-svg-export.png")).toBe(false);
  });
});
