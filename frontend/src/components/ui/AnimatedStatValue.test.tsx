import { describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import { AnimatedStatValue } from "./AnimatedStatValue";

// `useReducedMotion` dépend de `window.matchMedia`, non polyfillé dans
// l'environnement de test (cf. le même mock dans SkillsByCategory.test.tsx) —
// on le fixe à `false` plutôt que de le mocker à vide : ce fichier teste
// AnimatedStatValue lui-même (pas un composant enfant qu'on peut remplacer),
// donc son propre chemin d'exécution (l'IntersectionObserver ci-dessous)
// reste exercé tel quel.
vi.mock("@/lib/useReducedMotion", () => ({
  useReducedMotion: () => false,
}));

// Non polyfillé par jsdom.
class MockIntersectionObserver {
  observe() {}
  disconnect() {}
}
vi.stubGlobal("IntersectionObserver", MockIntersectionObserver);

describe("AnimatedStatValue", () => {
  it("renders the static suffix around a numeric prefix", () => {
    render(<AnimatedStatValue value="70% des prises de RDV" />);
    expect(screen.getByText(/des prises de RDV/)).toBeInTheDocument();
  });

  it("renders non-numeric text as-is", () => {
    render(<AnimatedStatValue value="Disponible 24/7" />);
    expect(screen.getByText("Disponible 24/7")).toBeInTheDocument();
  });

  // Régression : un projet en production a un jour eu un résultat saisi côté
  // admin sans valeur (label|valeur avec valeur vide — cf.
  // AdminProjectController::parsePairs, qui stocke `null` dans ce cas). Ça a
  // fait planter `next build` entier sur `value.match(...)` — cf. commit qui
  // introduit ce test.
  it("does not throw when value is null or undefined", () => {
    expect(() => render(<AnimatedStatValue value={null} />)).not.toThrow();
    expect(() => render(<AnimatedStatValue value={undefined} />)).not.toThrow();
  });
});
