import type { ReactNode } from "react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { act, fireEvent, render, screen, within } from "@testing-library/react";
import { OfferHeroCarousel } from "./OfferHeroCarousel";
import { makeOffer } from "./testOffers";

vi.mock("next-intl", () => ({
  useTranslations: () => (key: string, values?: Record<string, unknown>) =>
    values ? `${key}:${JSON.stringify(values)}` : key,
  useLocale: () => "fr",
}));

// Évite de dépendre du contexte de routing next-intl pour le lien « Voir
// l'offre » (/offres/[slug]).
vi.mock("@/i18n/navigation", () => ({
  Link: ({ href, children, ...props }: { href: unknown; children: ReactNode }) => (
    <a href={typeof href === "string" ? href : JSON.stringify(href)} {...props}>
      {children}
    </a>
  ),
}));

let reducedMotion = false;
vi.mock("@/lib/useReducedMotion", () => ({ useReducedMotion: () => reducedMotion }));

const offers = [
  makeOffer({ id: 1, slug: "a", shortTitle: "A", headline: "Titre A" }),
  makeOffer({ id: 2, slug: "b", shortTitle: "B", headline: "Titre B", heroPosition: 2 }),
  makeOffer({ id: 3, slug: "c", shortTitle: "C", headline: "Titre C", heroPosition: 3 }),
];

function slides() {
  return screen.getAllByRole("group", { hidden: true }).filter((el) => el.getAttribute("aria-roledescription") === "slide");
}
function activeSlide() {
  return slides().find((s) => !s.hasAttribute("inert"));
}
function activeHeadline() {
  return activeSlide()?.querySelector("h2")?.textContent;
}

describe("OfferHeroCarousel", () => {
  beforeEach(() => {
    vi.useFakeTimers();
    reducedMotion = false;
  });
  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  it("n'affiche rien sans offre", () => {
    const { container } = render(<OfferHeroCarousel offers={[]} />);
    expect(container).toBeEmptyDOMElement();
  });

  it("rend toutes les diapositives, une seule active, les autres inertes", () => {
    render(<OfferHeroCarousel offers={offers} />);
    expect(slides()).toHaveLength(3);
    expect(slides().filter((s) => !s.hasAttribute("inert"))).toHaveLength(1);
    expect(activeHeadline()).toBe("Titre A");
  });

  it("avance seule après 7 s", () => {
    render(<OfferHeroCarousel offers={offers} />);
    act(() => vi.advanceTimersByTime(7000));
    expect(activeHeadline()).toBe("Titre B");
  });

  it("ne défile plus une fois en pause (WCAG 2.2.2)", () => {
    render(<OfferHeroCarousel offers={offers} />);
    const pause = screen.getByRole("button", { name: "pause" });
    fireEvent.click(pause);
    expect(screen.getByRole("button", { name: "play" })).toHaveAttribute("aria-pressed", "true");
    act(() => vi.advanceTimersByTime(30000));
    expect(activeHeadline()).toBe("Titre A");
  });

  it("ne défile pas seul si l'utilisateur a réduit les animations", () => {
    reducedMotion = true;
    render(<OfferHeroCarousel offers={offers} />);
    act(() => vi.advanceTimersByTime(30000));
    expect(activeHeadline()).toBe("Titre A");
  });

  it("se met en pause au survol", () => {
    render(<OfferHeroCarousel offers={offers} />);
    fireEvent.mouseEnter(screen.getByRole("region", { name: "carouselLabel" }));
    act(() => vi.advanceTimersByTime(30000));
    expect(activeHeadline()).toBe("Titre A");
  });

  /** Simule `:focus-visible` (focus clavier ou souris) sans dépendre de jsdom. */
  function focusAs(kind: "keyboard" | "mouse", el: HTMLElement) {
    const original = Element.prototype.matches;
    vi.spyOn(Element.prototype, "matches").mockImplementation(function (this: Element, selector: string) {
      return selector === ":focus-visible" ? kind === "keyboard" : original.call(this, selector);
    });
    fireEvent.focus(el);
  }

  it("se met en pause quand le focus clavier entre dans le carrousel", () => {
    render(<OfferHeroCarousel offers={offers} />);
    focusAs("keyboard", screen.getByRole("button", { name: "next" }));
    act(() => vi.advanceTimersByTime(30000));
    expect(activeHeadline()).toBe("Titre A");
  });

  it("un clic souris sur « lecture » relance le défilement sans attendre la perte du focus", () => {
    render(<OfferHeroCarousel offers={offers} />);
    fireEvent.click(screen.getByRole("button", { name: "pause" }));
    const play = screen.getByRole("button", { name: "play" });
    focusAs("mouse", play);
    fireEvent.click(play);
    act(() => vi.advanceTimersByTime(7000));
    expect(activeHeadline()).toBe("Titre B");
  });

  it("précédent/suivant bouclent, les pastilles choisissent une offre", () => {
    render(<OfferHeroCarousel offers={offers} />);
    fireEvent.click(screen.getByRole("button", { name: "previous" }));
    expect(activeHeadline()).toBe("Titre C");
    fireEvent.click(screen.getByRole("button", { name: "next" }));
    expect(activeHeadline()).toBe("Titre A");
    fireEvent.click(screen.getByRole("button", { name: "B" }));
    expect(activeHeadline()).toBe("Titre B");
    expect(screen.getByRole("button", { name: "B" })).toHaveAttribute("aria-current", "true");
  });

  /**
   * R4 : « Voir l'offre » menait vers une ancre en page (#offre-<slug>)
   * avant que la fiche dédiée n'existe. Elle y renvoie désormais.
   */
  it("« Voir l'offre » pointe vers la fiche dédiée de l'offre active", () => {
    render(<OfferHeroCarousel offers={offers} />);
    const link = within(activeSlide()!).getByRole("link", { name: "seeOffer" });
    expect(link.getAttribute("href")).toContain('"slug":"a"');
    expect(link.getAttribute("href")).not.toMatch(/^#/);
  });

  it("sans contrôles ni défilement avec une seule offre", () => {
    render(<OfferHeroCarousel offers={[offers[0]]} />);
    expect(screen.queryByRole("button", { name: "next" })).toBeNull();
    act(() => vi.advanceTimersByTime(30000));
    expect(activeHeadline()).toBe("Titre A");
  });
});
