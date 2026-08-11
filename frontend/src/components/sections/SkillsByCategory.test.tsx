import { describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen } from "@testing-library/react";
import { SkillsByCategory } from "./SkillsByCategory";
import type { Skill } from "@/lib/types";

/**
 * `Reveal` dépend de `IntersectionObserver`/`matchMedia`, non polyfillés dans
 * l'environnement de test (aucun autre composant testé jusqu'ici n'y touchait) —
 * mocké en passe-plat pour isoler le comportement propre à l'accordéon, seul
 * sujet de ce fichier. `Badge` est réimporté depuis son propre module (pas le
 * barrel `@/components/ui`, dont d'autres exports tirent `next/navigation`,
 * non résolvable dans cet environnement de test).
 */
vi.mock("@/components/ui", async () => {
  const { Badge } = await import("@/components/ui/Badge");
  return {
    Badge,
    Reveal: ({ children }: { children: React.ReactNode }) => children,
  };
});

function skill(overrides: Partial<Skill> & { categoryId: number; categoryName: string }): Skill {
  const { categoryId, categoryName, ...rest } = overrides;
  return {
    id: rest.id ?? Math.random(),
    name: rest.name ?? "Compétence",
    level: rest.level ?? 5,
    skillCategory: { id: categoryId, name: categoryName },
  };
}

const SKILLS: Skill[] = [
  skill({ id: 1, name: "Symfony", level: 9, categoryId: 1, categoryName: "Développeur Web FullStack" }),
  skill({ id: 2, name: "Next.js", level: 8, categoryId: 1, categoryName: "Développeur Web FullStack" }),
  skill({ id: 3, name: "Flutter", level: 7, categoryId: 2, categoryName: "Développement Mobile" }),
];

describe("SkillsByCategory", () => {
  it("regroupe les compétences par catégorie et ouvre la première par défaut", () => {
    render(<SkillsByCategory skills={SKILLS} />);

    const web = screen.getByRole("button", { name: /Développeur Web FullStack/ });
    const mobile = screen.getByRole("button", { name: /Développement Mobile/ });
    expect(web).toHaveAttribute("aria-expanded", "true");
    expect(mobile).toHaveAttribute("aria-expanded", "false");
    // Compétences de la catégorie fermée absentes du DOM tant qu'elle n'est pas ouverte visuellement…
    expect(screen.getByText("Symfony")).toBeVisible();
  });

  it("ouvre/ferme une catégorie au clic, sans fermer les autres (ouverture multiple)", () => {
    render(<SkillsByCategory skills={SKILLS} />);

    const mobile = screen.getByRole("button", { name: /Développement Mobile/ });
    fireEvent.click(mobile);

    expect(mobile).toHaveAttribute("aria-expanded", "true");
    // La première catégorie reste ouverte — pas d'ouverture exclusive.
    expect(screen.getByRole("button", { name: /Développeur Web FullStack/ })).toHaveAttribute(
      "aria-expanded",
      "true",
    );

    fireEvent.click(mobile);
    expect(mobile).toHaveAttribute("aria-expanded", "false");
  });

  it("expose le niveau de chaque compétence via aria-valuetext (pas seulement visuel)", () => {
    render(<SkillsByCategory skills={SKILLS} />);

    const gauge = screen.getByRole("progressbar", { name: "Symfony" });
    expect(gauge).toHaveAttribute("aria-valuenow", "9");
    expect(gauge).toHaveAttribute("aria-valuemin", "1");
    expect(gauge).toHaveAttribute("aria-valuemax", "10");
    expect(gauge).toHaveAttribute("aria-valuetext", "Niveau 9 sur 10");
  });

  it("tronque aux `maxSkillsPerCategory` compétences les mieux notées", () => {
    render(<SkillsByCategory skills={SKILLS} maxSkillsPerCategory={1} />);

    // Développeur Web FullStack, triée par niveau décroissant : Symfony (9) > Next.js (8).
    expect(screen.getByText("Symfony")).toBeInTheDocument();
    expect(screen.queryByText("Next.js")).not.toBeInTheDocument();
  });

  it("featuredCategories sélectionne et ordonne un sous-ensemble, insensible à la locale/casse/accents", () => {
    render(
      <SkillsByCategory
        skills={SKILLS}
        featuredCategories={[
          ["Mobile Development", "Développement Mobile"], // ordre inversé par rapport aux données
          ["developpeur web fullstack"], // sans accent, casse différente
        ]}
      />,
    );

    const buttons = screen.getAllByRole("button", { name: /Développement|FullStack/ });
    expect(buttons.map((b) => b.textContent)).toEqual([
      expect.stringContaining("Développement Mobile"),
      expect.stringContaining("Développeur Web FullStack"),
    ]);
  });

  it("featuredCategories ignore un slot sans correspondance, sans erreur", () => {
    render(<SkillsByCategory skills={SKILLS} featuredCategories={[["Catégorie inexistante"]]} />);
    expect(screen.queryByRole("button")).not.toBeInTheDocument();
  });

  it("ne rend rien quand aucune compétence n'est fournie", () => {
    const { container } = render(<SkillsByCategory skills={[]} />);
    expect(container).toBeEmptyDOMElement();
  });
});
