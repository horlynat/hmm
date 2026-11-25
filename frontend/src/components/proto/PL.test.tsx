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

/**
 * URL finale réelle (vrai Link next-intl, sans mock) : c'est elle que le
 * visiteur suit — l'objet href seul ne suffit pas (5e contre-audit).
 */
describe("PL — URL finale du lien « Envoyer un message »", () => {
  it.each([
    ["fr", "/fr/contact?mode=direct"],
    ["en", "/en/contact?mode=direct"],
  ])("en %s, mène à %s", async (locale, expected) => {
    const { render } = await import("@testing-library/react");
    const { NextIntlClientProvider } = await import("next-intl");
    const { PL } = await import("./PL");
    const { container, unmount } = render(
      <NextIntlClientProvider locale={locale} messages={{}}>
        <PL href="/contact?mode=direct">lien</PL>
      </NextIntlClientProvider>,
    );

    expect(container.querySelector("a")).toHaveAttribute("href", expected);
    unmount();
  });
});
