import { describe, expect, it } from "vitest";
import { formatFcfa } from "./fcfa";

describe("formatFcfa", () => {
  // Les séparateurs de milliers d'Intl varient (espace fine insécable en
  // français) : on normalise tous les blancs pour comparer les chiffres.
  const norm = (s: string) => s.replace(/\s/g, " ");

  it("formate en français avec séparateur de milliers", () => {
    expect(norm(formatFcfa(199999, "fr"))).toBe("199 999 FCFA");
    expect(norm(formatFcfa(1500000, "fr"))).toBe("1 500 000 FCFA");
  });

  it("formate en anglais avec virgule", () => {
    expect(formatFcfa(199999, "en")).toBe("199,999\u202fFCFA");
  });

  it("ne coupe jamais l'unité de la somme (espace insécable)", () => {
    expect(formatFcfa(90000, "fr")).toBe("90\u202f000\u202fFCFA");
  });

  it("n'affiche pas de décimales", () => {
    expect(norm(formatFcfa(0, "fr"))).toBe("0 FCFA");
  });
});
