import { afterEach, describe, expect, it, vi } from "vitest";
import { act, fireEvent, render, screen, within } from "@testing-library/react";
import { OffersSection, situationFromHash } from "./OffersSection";
import { makeOffer } from "./testOffers";

vi.mock("next-intl", () => ({
  useTranslations: () => (key: string, values?: Record<string, unknown>) =>
    values ? `${key}:${JSON.stringify(values)}` : key,
  useLocale: () => "fr",
}));

const offers = [
  makeOffer({ id: 1, slug: "whatsapp", title: "Assistant", situation: "clients" }),
  makeOffer({ id: 2, slug: "audit", title: "Audit", situation: "secu", heroPosition: 2 }),
  makeOffer({ id: 3, slug: "risques", title: "Risques", situation: "assur", heroPosition: null }),
];

function titles() {
  return screen.getAllByRole("heading", { level: 3 }).map((h) => h.textContent);
}

describe("situationFromHash", () => {
  it.each([
    ["#offres-clients", "clients"],
    ["#offres-secu", "secu"],
  ])("accepte %s", (hash, expected) => {
    expect(situationFromHash(hash)).toBe(expected);
  });

  // Ancre forgée dans un lien : ne doit jamais sortir de la liste blanche.
  it.each(["", "#offres", "#offres-", "#offres-inconnu", "#offres-secu-x", "#offres-SECU", "#offres-secu\n", "#x-offres-secu", "#offres-__proto__", "#offres-constructor"])(
    "refuse %j",
    (hash) => {
      expect(situationFromHash(hash)).toBeNull();
    },
  );
});

describe("OffersSection", () => {
  afterEach(() => {
    window.history.replaceState(null, "", "/");
  });

  it("affiche toutes les offres par défaut", () => {
    render(<OffersSection offers={offers} />);
    expect(titles()).toEqual(["Assistant", "Audit", "Risques"]);
  });

  it("filtre au clic sur une situation, puis revient à toutes", () => {
    render(<OffersSection offers={offers} />);
    const filters = screen.getByRole("group", { name: "filterLabel" });

    fireEvent.click(within(filters).getByRole("button", { name: "filters.secu" }));
    expect(titles()).toEqual(["Audit"]);
    expect(within(filters).getByRole("button", { name: "filters.secu" })).toHaveAttribute("aria-pressed", "true");

    fireEvent.click(within(filters).getByRole("button", { name: "filterAll" }));
    expect(titles()).toEqual(["Assistant", "Audit", "Risques"]);
  });

  it("applique le filtre porté par l'ancre (cartes « Votre besoin »)", () => {
    render(<OffersSection offers={offers} />);
    act(() => {
      window.location.hash = "#offres-assur";
      window.dispatchEvent(new HashChangeEvent("hashchange"));
    });
    expect(titles()).toEqual(["Risques"]);
  });

  it("une nouvelle ancre remplace un choix fait au bouton", () => {
    render(<OffersSection offers={offers} />);
    fireEvent.click(screen.getByRole("button", { name: "filters.secu" }));
    expect(titles()).toEqual(["Audit"]);

    act(() => {
      window.location.hash = "#offres-clients";
      window.dispatchEvent(new HashChangeEvent("hashchange"));
    });
    expect(titles()).toEqual(["Assistant"]);
  });

  it("annonce l'absence d'offre pour une situation sans offre", () => {
    render(<OffersSection offers={offers} />);
    fireEvent.click(screen.getByRole("button", { name: "filters.appli" }));
    expect(screen.getByText("noMatch")).toBeInTheDocument();
  });

  it("ancre chaque carte sur son slug (lien « Voir l'offre » du carrousel)", () => {
    const { container } = render(<OffersSection offers={offers} />);
    expect(container.querySelector("#offre-audit")).not.toBeNull();
  });

  /**
   * Le contenu des offres vient du back-office : il doit être rendu comme du
   * TEXTE, jamais interprété comme du HTML (aucun dangerouslySetInnerHTML).
   */
  it("n'interprète jamais le contenu d'une offre comme du HTML", () => {
    const evil = '<img src=x onerror="window.__pwned=1"><script>window.__pwned=1</script>';
    const { container } = render(
      <OffersSection
        offers={[makeOffer({ title: evil, cardText: evil, pain: evil, audience: evil, includes: [evil], calendar: [{ day: "J1", text: evil }], pack: [{ title: evil, items: [evil] }] })]}
      />,
    );
    expect(container.querySelector("img, script")).toBeNull();
    expect(screen.getAllByText(evil, { exact: false }).length).toBeGreaterThan(0);
    expect((window as unknown as { __pwned?: number }).__pwned).toBeUndefined();
  });

  it("le lien de commande WhatsApp garde le titre dans le seul paramètre text", () => {
    render(<OffersSection offers={[makeOffer({ title: "A & B #1 ?x=y" })]} />);
    const link = screen.getByRole("link", { name: /order/ });
    const url = new URL(link.getAttribute("href") ?? "");
    expect(url.host).toBe("wa.me");
    expect([...url.searchParams.keys()]).toEqual(["text"]);
    expect(url.searchParams.get("text")).toContain("A & B #1 ?x=y");
    expect(link).toHaveAttribute("rel", "noopener noreferrer");
  });
});
