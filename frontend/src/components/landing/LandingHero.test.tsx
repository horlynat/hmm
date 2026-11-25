import { afterEach, describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import fr from "@/messages/fr.json";

vi.mock("next-intl", async (importOriginal) => {
  const actual = await importOriginal<typeof import("next-intl")>();
  return {
    ...actual,
    useTranslations: (namespace: string) => actual.createTranslator({ locale: "fr", messages: fr, namespace: namespace as never }),
    useLocale: () => "fr",
  };
});
vi.mock("@/lib/useReducedMotion", () => ({ useReducedMotion: () => true }));

const { LandingHero } = await import("./LandingHero");

/**
 * Badge d'identité du hero (`.by2`) : photo réelle de la page À propos
 * (back-office) si elle existe, sinon le médaillon dessiné du prototype —
 * jamais les deux. Testé sur la branche sans offre (`total === 0`), plus
 * simple : c'est la même constante `identity` dans les deux branches.
 */
describe("LandingHero — badge d'identité", () => {
  afterEach(() => vi.restoreAllMocks());

  it("sans profilePhotoUrl, affiche le médaillon dessiné du prototype", () => {
    render(<LandingHero offers={[]} />);

    expect(document.querySelector(".byr svg")).toBeInTheDocument();
    expect(screen.queryByRole("img", { name: fr.landing.identity.name })).not.toBeInTheDocument();
  });

  it("avec profilePhotoUrl, affiche la vraie photo (jamais le médaillon dessiné)", () => {
    render(<LandingHero offers={[]} profilePhotoUrl="http://127.0.0.1:8000/uploads/about/photo.jpg" />);

    const img = screen.getByRole("img", { name: fr.landing.identity.name });
    expect(img.getAttribute("src")).toContain("photo.jpg");
    expect(document.querySelector(".byr svg")).not.toBeInTheDocument();
  });

  it("la photo reste dans la zone de contenu du badge : l'anneau dégradé (padding de .byr) reste visible", () => {
    render(<LandingHero offers={[]} profilePhotoUrl="http://127.0.0.1:8000/uploads/about/photo.jpg" />);

    const img = screen.getByRole("img", { name: fr.landing.identity.name });
    // Enfant direct, pour que la règle `.byr>img` du prototype s'applique…
    expect(img.parentElement).toHaveClass("byr");
    // …et jamais positionnée en absolu (next/image `fill`), qui couvrirait le padding.
    expect(img.style.position).not.toBe("absolute");
  });
});
