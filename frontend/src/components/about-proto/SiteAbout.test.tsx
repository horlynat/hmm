import { beforeEach, describe, expect, it, vi } from "vitest";
import { render } from "@testing-library/react";
import { createTranslator } from "next-intl";
import fr from "@/messages/fr.json";
import type { AboutContent, SkillDomainContent } from "@/lib/types";

vi.mock("next-intl/server", () => ({
  getTranslations: async ({ namespace }: { namespace: string }) =>
    createTranslator({ locale: "fr", messages: fr, namespace: namespace as never }),
}));
vi.mock("@/i18n/navigation", () => ({
  Link: ({ href, children, ...rest }: { href: unknown; children: React.ReactNode }) => (
    <a href={typeof href === "string" ? href : JSON.stringify(href)} {...rest}>
      {children}
    </a>
  ),
}));
vi.mock("@/components/proto/ProtoSections", () => ({ ProtoCta: ({ title }: { title?: string }) => <div data-testid="cta">{title}</div> }));
vi.mock("@/components/landing/SiteHome", () => ({ ProjectsShowcase: () => <div data-testid="projects" /> }));
vi.mock("./SkillTabs", () => ({
  SkillTabs: ({ domains, note }: { domains: { title: string; offers: { slug: string }[]; proof: unknown }[]; note?: string }) => (
    <div data-testid="tabs" data-note={note}>
      {domains.map((d) => (
        <span key={d.title} data-offers={d.offers.map((o) => o.slug).join(",")} data-proof={JSON.stringify(d.proof)}>
          {d.title}
        </span>
      ))}
    </div>
  ),
}));

const about = vi.fn();
const domains = vi.fn();
vi.mock("@/lib/api/about-content", () => ({ getAboutContent: () => about() }));
vi.mock("@/lib/api/skill-domains", () => ({ getSkillDomains: () => domains() }));
const experiences = vi.fn();
const courses = vi.fn();
const projects = vi.fn();
const strictFlags: unknown[] = [];
vi.mock("@/lib/api/experiences", () => ({ getExperiences: (_l: string, o: unknown) => (strictFlags.push(o), experiences()) }));
vi.mock("@/lib/api/courses", () => ({ getCourses: (_l: string, o: unknown) => (strictFlags.push(o), courses()) }));
vi.mock("@/lib/api/offers", () => ({ getOffers: async (_l: string, o: unknown) => (strictFlags.push(o), [{ slug: "audit", title: "Audit" }]) }));
vi.mock("@/lib/api/projects", () => ({ getProjects: (_l: string, o: unknown) => (strictFlags.push(o), projects()) }));

const { SiteAbout } = await import("./SiteAbout");

function content(over: Partial<AboutContent> = {}): AboutContent {
  const base = Object.fromEntries(
    ["heroTitle", "heroTitleAccent", "heroSub", "profileName", "profileRole", "profileAvailability", "bioTitle", "bioP1", "bioP2", "visionLede", "visionTodayText", "visionTomorrowText",
      "why1Title", "why1Desc", "why2Title", "why2Desc", "why3Title", "why3Desc", "why4Title", "why4Desc", "why5Title", "why5Desc", "ctaTitle", "ctaSub",
      "visionPillar", "missionPillar", "ambitionPillar", "horizonMidText", "learningText",
      "skillsTitle", "skillsLede", "skillsNote", "projectsTitle", "projectsLede", "pathTitle", "pathLede"].map((k) => [k, `${k} texte`]),
  );
  return { ...base, profileImagePath: null, journeyStats: [], ...over } as AboutContent;
}

const domain: SkillDomainContent = {
  icon: "lock", title: "Cybersécurité", short: "s", text: "t", do: [], tools: [], offerSlugs: ["audit", "inconnue"], proofProjectSlug: "portail",
};

describe("SiteAbout — page À propos pilotée par le back-office", () => {
  beforeEach(() => {
    about.mockResolvedValue(content({ heroTitle: "Ingénieur et", heroTitleAccent: "architecte logiciel." }));
    domains.mockResolvedValue([domain]);
    experiences.mockResolvedValue([]);
    courses.mockResolvedValue([]);
    projects.mockResolvedValue([{ slug: "portail", title: "Portail", status: "termine" }]);
    strictFlags.length = 0;
  });

  it("titre, sections et domaines viennent du back-office (plus de textes en dur)", async () => {
    const { container, getByTestId } = render(await SiteAbout({ locale: "fr" }));

    expect(container.querySelector("h1")?.textContent).toBe("Ingénieur et architecte logiciel.");
    const h2 = [...container.querySelectorAll("h2")].map((h) => h.textContent);
    expect(h2).toEqual(expect.arrayContaining(["bioTitle texte", "skillsTitle texte", "projectsTitle texte", "pathTitle texte"]));
    expect(container.textContent).toContain("skillsLede texte");
    expect(getByTestId("tabs").dataset.note).toBe("skillsNote texte");
    // Offre inconnue ignorée, preuve résolue sur la réalisation publiée.
    const tab = getByTestId("tabs").querySelector("span")!;
    expect(tab.textContent).toBe("Cybersécurité");
    expect(tab.dataset.offers).toBe("audit");
    expect(JSON.parse(tab.dataset.proof!)).toEqual({ slug: "portail", title: "Portail" });
    expect(getByTestId("cta").textContent).toBe("ctaTitle texte");
  });

  it("un titre de section vide masque l'élément, aucun domaine masque la section", async () => {
    about.mockResolvedValue(content({ pathTitle: "", skillsTitle: "" }));
    domains.mockResolvedValue([]);
    const { container } = render(await SiteAbout({ locale: "fr" }));

    expect(container.querySelector("#competences")).toBeNull();
    expect(container.querySelector("#parcours h2")).toBeNull();
    expect(container.querySelector("#parcours .sh p")?.textContent).toBe("pathLede texte");
  });

  it("API injoignable : refuse de générer une page vide (la dernière bonne version reste en cache)", async () => {
    about.mockResolvedValue(null);
    await expect(SiteAbout({ locale: "fr" })).rejects.toThrow(/indisponible/);

    about.mockResolvedValue(content());
    domains.mockResolvedValue(null);
    await expect(SiteAbout({ locale: "fr" })).rejects.toThrow(/indisponible/);
  });

  it("toutes les listes sont lues en mode strict : une API en panne bloque la régénération", async () => {
    render(await SiteAbout({ locale: "fr" }));
    expect(strictFlags).toEqual([{ strict: true }, { strict: true }, { strict: true }, { strict: true }]);

    projects.mockRejectedValue(new Error("API /projects indisponible."));
    await expect(SiteAbout({ locale: "fr" })).rejects.toThrow(/indisponible/);
  });

  it("sous-menu : jamais de lien vers une section masquée ; colonnes vides du parcours masquées", async () => {
    domains.mockResolvedValue([]);
    projects.mockResolvedValue([]);
    about.mockResolvedValue(content({ learningText: "" }));
    const { container } = render(await SiteAbout({ locale: "fr" }));

    const links = [...container.querySelectorAll("nav.sub a")].map((a) => a.getAttribute("href") ?? "");
    expect(links.some((h) => h.includes("competences"))).toBe(false);
    expect(links.some((h) => h.includes("realisations"))).toBe(false);
    expect(links.some((h) => h.includes("parcours"))).toBe(true);
    expect(container.querySelectorAll(".pgrid > div")).toHaveLength(0);

    experiences.mockResolvedValue([{ id: 1, role: "Responsable", company: "X", startDate: "2021-01-01", endDate: null, description: "" }]);
    const again = render(await SiteAbout({ locale: "fr" }));
    expect(again.container.querySelectorAll(".pgrid > div")).toHaveLength(1);
  });
});
