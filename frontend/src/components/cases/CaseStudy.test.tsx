import { describe, expect, it, vi } from "vitest";
import { render } from "@testing-library/react";
import { createTranslator } from "next-intl";
import fr from "@/messages/fr.json";
import type { Project, ProjectInfo } from "@/lib/types";

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
vi.mock("@/components/proto/ProtoSections", () => ({ ProtoCta: () => null }));
vi.mock("@/components/landing/SiteHome", () => ({ ProjectImage: () => <span data-testid="project-art" /> }));

const { CaseStudy } = await import("./CaseStudy");

const emptyInfo: ProjectInfo = {
  role: null,
  objectives: [],
  techStack: [],
  challenges: [],
  results: [],
  repoUrl: null,
  coverImage: null,
  architectureDiagram: null,
  result: null,
  brief: [],
  context: null,
  stake: null,
  steps: [],
  solution: null,
  deliverables: [],
  galleryCaptions: [],
  discreet: false,
  relatedOfferSlug: null,
};

function project(info: Partial<ProjectInfo> | null, description = "<p>Description du projet.</p>"): Project {
  return {
    id: 1,
    slug: "dbg",
    title: "Plateforme",
    description,
    link: "",
    status: "in_progress",
    priority: null,
    billingType: null,
    progress: 0,
    media: [],
    info: info ? { ...emptyInfo, ...info } : null,
  };
}

async function renderCase(p: Project) {
  return render(await CaseStudy({ locale: "fr", project: p, next: null, offer: null }));
}

describe("CaseStudy — récit du prototype (pCase)", () => {
  it("rend résultat, bref (3 au plus), point de départ, démarche, livrables, galerie et note discrète", async () => {
    const { container, getByText, getAllByText } = await renderCase(
      project({
        result: "Une société opérationnelle dès son lancement.",
        brief: [
          { title: "La situation", text: "A" },
          { title: "Ce que j’ai fait", text: "B" },
          { title: "Ce que cela a changé", text: "C" },
          { title: "En trop", text: "D" },
        ],
        context: "Le contexte.",
        stake: "Le défi.",
        steps: [{ title: "Cadrage", text: "Lister les publics." }],
        solution: "Une plateforme unique.",
        deliverables: ["Site public", "Espace client"],
        galleryCaptions: ["Site public de la société", "Espace client", "Tableau de pilotage"],
        discreet: true,
      }),
    );
    expect(container.querySelector(".res")?.textContent).toBe("Une société opérationnelle dès son lancement.");
    expect(container.querySelectorAll(".br3 > div")).toHaveLength(3);
    expect([...container.querySelectorAll(".chap p.t")].map((p) => p.textContent)).toEqual(["Le contexte.", "Le défi.", "Une plateforme unique."]);
    expect(container.querySelector(".t-rich")).toBeNull(); // le récit remplace la description HTML
    expect(container.querySelector(".road li p")?.textContent).toBe("Lister les publics.");
    expect(container.querySelectorAll(".liv > div")).toHaveLength(2);
    expect(container.querySelectorAll(".gal figure")).toHaveLength(3);
    expect(container.querySelector(".gal figure [data-testid='project-art']")).not.toBeNull();
    expect(getAllByText("Capture réelle à venir")).toHaveLength(2);
    expect(getByText("Pourquoi certains détails ne sont pas publiés")).toBeTruthy();
  });

  it("retombe sur les anciens champs d'une fiche antérieure au récit", async () => {
    const { container } = await renderCase(
      project({ objectives: ["Simplifier le parcours"], challenges: [{ problem: "Pic", solution: "Verrou optimiste" }] }),
    );
    expect(container.querySelector(".res")).toBeNull();
    expect(container.querySelector(".br3")).toBeNull();
    expect(container.querySelector(".t-rich")?.textContent).toBe("Description du projet.");
    expect(container.querySelector(".road li b")?.textContent).toBe("Simplifier le parcours");
    expect(container.querySelector(".liv span")?.textContent).toBe("Verrou optimiste");
    expect(container.querySelector(".gal")).toBeNull();
    expect(container.querySelector(".disc")).toBeNull();
  });

  it("n'interprète jamais le texte du back-office comme du HTML", async () => {
    const evil = '<img src=x onerror="alert(1)">';
    const { container } = await renderCase(
      project({ result: evil, context: evil, brief: [{ title: evil, text: evil }], steps: [{ title: evil, text: evil }], deliverables: [evil], galleryCaptions: [evil] }),
    );
    expect(container.querySelector("img[onerror]")).toBeNull();
    expect(container.querySelector(".res")?.textContent).toBe(evil);
  });
});
