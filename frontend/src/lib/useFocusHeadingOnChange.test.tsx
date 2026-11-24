import { describe, expect, it, vi, beforeEach } from "vitest";
import { render } from "@testing-library/react";
import { useFocusHeadingOnChange } from "./useFocusHeadingOnChange";

// `useReducedMotion` dépend de `window.matchMedia`, non polyfillé par jsdom
// (même mock que AnimatedStatValue.test.tsx / SkillsByCategory.test.tsx).
vi.mock("./useReducedMotion", () => ({
  useReducedMotion: () => false,
}));

/**
 * Écran de test : reproduit la structure réelle du questionnaire de devis,
 * où le champ piège anti-robots (`.hp`, tabIndex=-1) PRÉCÈDE le titre de
 * l'étape dans le DOM.
 */
function Screen({ step }: { step: number }) {
  const ref = useFocusHeadingOnChange(step);
  return (
    <div ref={ref}>
      <div className="hp" aria-hidden="true">
        <label htmlFor="hp">Ne pas remplir</label>
        <input id="hp" data-testid="honeypot" tabIndex={-1} />
      </div>
      <h3 className="qq" tabIndex={-1} data-testid="heading">
        Étape {step}
      </h3>
      <button type="button" data-testid="next">
        Suivant
      </button>
    </div>
  );
}

describe("useFocusHeadingOnChange", () => {
  beforeEach(() => {
    // jsdom n'implémente pas scrollTo : on l'observe sans effet de bord.
    window.scrollTo = vi.fn() as unknown as typeof window.scrollTo;
  });

  it("does not steal focus on the first render", () => {
    // Assertion immédiatement après le montage, sans déplacer le focus
    // ailleurs avant : un `.focus()` intermédiaire masquerait un vol de
    // focus survenu au montage (cas constaté en test de mutation).
    const { getByTestId } = render(<Screen step={1} />);
    expect(document.activeElement).not.toBe(getByTestId("heading"));
    expect(document.activeElement).toBe(document.body);
    expect(window.scrollTo).not.toHaveBeenCalled();
  });

  it("moves focus to the new screen's heading when the screen changes", () => {
    const { getByTestId, rerender } = render(<Screen step={1} />);
    getByTestId("next").focus();
    rerender(<Screen step={2} />);
    expect(document.activeElement).toBe(getByTestId("heading"));
    expect(getByTestId("heading").textContent).toContain("Étape 2");
  });

  it("never focuses the anti-bot honeypot, even though it comes first in the DOM", () => {
    // Régression : un sélecteur `[tabindex='-1']` générique visait le piège,
    // et un visiteur qui tapait ensuite voyait sa demande ignorée en silence.
    const { getByTestId, rerender } = render(<Screen step={1} />);
    rerender(<Screen step={2} />);
    expect(document.activeElement).not.toBe(getByTestId("honeypot"));
    expect(document.activeElement).toBe(getByTestId("heading"));
  });

  it("does not refocus when re-rendered with the same screen", () => {
    const { getByTestId, rerender } = render(<Screen step={1} />);
    rerender(<Screen step={2} />);
    getByTestId("next").focus();
    rerender(<Screen step={2} />);
    expect(document.activeElement).toBe(getByTestId("next"));
  });
});
