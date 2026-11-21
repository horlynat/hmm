import type { ReactNode } from "react";
import { describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen, within } from "@testing-library/react";
import { OffresGrid } from "./OffresGrid";
import { makeOffer } from "@/components/home/testOffers";

vi.mock("next-intl", () => ({
  useTranslations: () => (key: string, values?: Record<string, unknown>) =>
    values ? `${key}:${JSON.stringify(values)}` : key,
  useLocale: () => "fr",
}));

vi.mock("@/i18n/navigation", () => ({
  Link: ({ href, children, ...props }: { href: unknown; children: ReactNode }) => (
    <a href={typeof href === "string" ? href : JSON.stringify(href)} {...props}>
      {children}
    </a>
  ),
}));

const offers = [
  makeOffer({ id: 1, slug: "whatsapp", title: "Assistant", situation: "clients" }),
  makeOffer({ id: 2, slug: "audit", title: "Audit", situation: "secu", featured: true }),
  makeOffer({ id: 3, slug: "risques", title: "Risques", situation: "assur" }),
];

function titles() {
  return screen.getAllByRole("heading", { level: 2 }).map((h) => h.textContent);
}

describe("OffresGrid", () => {
  it("affiche toutes les offres par défaut", () => {
    render(<OffresGrid offers={offers} />);
    expect(titles()).toEqual(["Assistant", "Audit", "Risques"]);
  });

  it("filtre au clic sur une situation, puis revient à toutes", () => {
    render(<OffresGrid offers={offers} />);
    const filters = screen.getByRole("group", { name: "filterLabel" });

    fireEvent.click(within(filters).getByRole("button", { name: "filters.secu" }));
    expect(titles()).toEqual(["Audit"]);
    expect(within(filters).getByRole("button", { name: "filters.secu" })).toHaveAttribute("aria-pressed", "true");

    fireEvent.click(within(filters).getByRole("button", { name: "filterAll" }));
    expect(titles()).toEqual(["Assistant", "Audit", "Risques"]);
  });

  it("annonce l'absence d'offre pour une situation sans offre", () => {
    render(<OffresGrid offers={offers} />);
    fireEvent.click(screen.getByRole("button", { name: "filters.appli" }));
    expect(screen.getByText("noMatch")).toBeInTheDocument();
  });

  it("chaque carte renvoie vers la fiche dédiée de l'offre, jamais une ancre", () => {
    render(<OffresGrid offers={offers} />);
    const card = screen.getByRole("heading", { level: 2, name: "Audit" }).closest("article")!;
    const link = within(card).getByRole("link", { name: "seeDetail" });
    expect(link.getAttribute("href")).toContain('"slug":"audit"');
    expect(link.getAttribute("href")).not.toMatch(/^#/);
  });

  /**
   * Le contenu des offres vient du back-office : il doit être rendu comme du
   * TEXTE, jamais interprété comme du HTML (aucun dangerouslySetInnerHTML).
   */
  it("n'interprète jamais le contenu d'une offre comme du HTML", () => {
    const evil = '<img src=x onerror="window.__pwned=1"><script>window.__pwned=1</script>';
    const { container } = render(
      <OffresGrid offers={[makeOffer({ title: evil, cardText: evil, pain: evil, audience: evil })]} />,
    );
    expect(container.querySelector("img, script")).toBeNull();
    expect(screen.getAllByText(evil, { exact: false }).length).toBeGreaterThan(0);
    expect((window as unknown as { __pwned?: number }).__pwned).toBeUndefined();
  });

  it("le lien de commande WhatsApp garde le titre dans le seul paramètre text", () => {
    render(<OffresGrid offers={[makeOffer({ title: "A & B #1 ?x=y" })]} />);
    const link = screen.getByRole("link", { name: /order/ });
    const url = new URL(link.getAttribute("href") ?? "");
    expect(url.host).toBe("wa.me");
    expect([...url.searchParams.keys()]).toEqual(["text"]);
    expect(url.searchParams.get("text")).toContain("A & B #1 ?x=y");
    expect(link).toHaveAttribute("rel", "noopener noreferrer");
  });
});
