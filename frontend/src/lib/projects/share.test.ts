import { describe, expect, it } from "vitest";
import { pageTitle, shareDescription, shareImage } from "./share";

describe("partage d'une réalisation", () => {
  it("n'utilise jamais une couverture SVG comme image de partage", () => {
    expect(shareImage("/uploads/projects/cover.svg", "fr")).toMatch(/\/fr\/opengraph-image$/);
    expect(shareImage("/uploads/projects/cover.SVG?v=2", "en")).toMatch(/\/en\/opengraph-image$/);
    expect(shareImage(null, "fr")).toMatch(/\/fr\/opengraph-image$/);
    expect(shareImage("/uploads/projects/cover.png", "fr")).toMatch(/\/uploads\/projects\/cover\.png$/);
  });

  it("décrit par le résultat, sinon le premier paragraphe, sans couper un mot", () => {
    expect(shareDescription("Un portail en production.", "<p>Autre</p>")).toBe("Un portail en production.");
    expect(shareDescription(null, "<h2>Le projet</h2><p>Premier. Second.</p>")).toBe("Premier. Second.");
    const long = shareDescription(null, `<p>${"mot ".repeat(80)}</p>`);
    expect(long.length).toBeLessThanOrEqual(160);
    expect(long.endsWith("mot…")).toBe(true);
  });

  it("évite « Horlynat | Portail — Horlynat »", () => {
    expect(pageTitle("Horlynat | Portail Digital")).toEqual({ absolute: "Horlynat | Portail Digital" });
    expect(pageTitle("Plateforme DBG")).toBe("Plateforme DBG");
  });
});
