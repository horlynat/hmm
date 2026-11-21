import { describe, expect, it } from "vitest";
import { whatsappLink } from "./whatsapp";

describe("whatsappLink", () => {
  it("renvoie le lien nu sans message", () => {
    expect(whatsappLink()).toBe("https://wa.me/242066429090");
    expect(whatsappLink("   ")).toBe("https://wa.me/242066429090");
  });

  it("encode le message dans le paramètre text", () => {
    const url = new URL(whatsappLink("Bonjour Horlynat, je suis intéressé par l'offre « Site vitrine »."));
    expect(url.origin + url.pathname).toBe("https://wa.me/242066429090");
    expect(url.searchParams.get("text")).toBe("Bonjour Horlynat, je suis intéressé par l'offre « Site vitrine ».");
  });

  /**
   * Le titre d'une offre vient du back-office : un `&`, `#`, `?` ou `=` ne
   * doit jamais ajouter un paramètre, changer d'hôte ni tronquer le message.
   */
  it.each([
    "Offre A & B",
    "Offre #1",
    "Offre ?x=1",
    "a=b&phone=000",
    "https://evil.example/",
    "Ligne 1\nLigne 2",
  ])("ne laisse pas « %s » sortir du paramètre text", (title) => {
    const url = new URL(whatsappLink(`Intéressé par « ${title} »`));
    expect(url.host).toBe("wa.me");
    expect(url.pathname).toBe("/242066429090");
    expect(url.hash).toBe("");
    expect([...url.searchParams.keys()]).toEqual(["text"]);
    expect(url.searchParams.get("text")).toBe(`Intéressé par « ${title} »`);
  });

  it("encode les espaces en %20 (format documenté par WhatsApp)", () => {
    expect(whatsappLink("Bonjour Horlynat")).toBe("https://wa.me/242066429090?text=Bonjour%20Horlynat");
  });

  it("ne lève pas sur un caractère UTF-16 isolé (titre corrompu)", () => {
    const link = whatsappLink("Offre \uD800 cassée");
    const url = new URL(link);
    expect(url.host).toBe("wa.me");
    expect([...url.searchParams.keys()]).toEqual(["text"]);
    expect(url.searchParams.get("text")).toBe("Offre \uFFFD cassée");
  });
});
