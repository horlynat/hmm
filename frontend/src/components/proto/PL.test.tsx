import { describe, expect, it } from "vitest";
import { protoToSite } from "./PL";

/**
 * Contre-audit : « /contact?mode=direct » (lien « Envoyer un message » de
 * l'assistant) menait à l'accueil — la requête n'était pas découpée.
 */
describe("protoToSite", () => {
  it("garde mode=direct sur /contact (onglet Message rapide)", () => {
    expect(protoToSite("/contact?mode=direct")).toEqual({ pathname: "/contact", query: { mode: "direct" } });
  });

  it("ignore toute autre requête sur /contact (jamais propagée)", () => {
    expect(protoToSite("/contact?mode=quest")).toEqual({ pathname: "/contact" });
    expect(protoToSite("/contact?next=https://evil.example")).toEqual({ pathname: "/contact" });
    expect(protoToSite("/contact")).toEqual({ pathname: "/contact" });
  });

  it("les autres routes sont inchangées", () => {
    expect(protoToSite("/offres/site")).toEqual({ pathname: "/offres/[slug]", params: { slug: "site" } });
    expect(protoToSite("/a-propos#competences")).toEqual({ pathname: "/a-propos", hash: "competences" });
    expect(protoToSite("/inconnu")).toEqual({ pathname: "/" });
  });
});
