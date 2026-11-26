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
    status: "en_cours",
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
    expect(container.querySelectorAll(".brief .br3 > div")).toHaveLength(3);
    expect([...container.querySelectorAll(".chap p.t")].map((p) => p.textContent)).toEqual(["Le contexte.", "Le défi.", "Une plateforme unique."]);
    expect(container.querySelector(".t-rich")).toBeNull(); // le récit remplace la description HTML
    expect(container.querySelector(".road li p")?.textContent).toBe("Lister les publics.");
    expect(container.querySelectorAll(".liv > div")).toHaveLength(2);
    expect(container.querySelectorAll(".gal figure")).toHaveLength(3);
    expect(container.querySelector(".gal figure [data-testid='project-art']")).not.toBeNull();
    expect(getAllByText("Capture réelle à venir")).toHaveLength(2);
    expect(getByText("Pourquoi certains détails ne sont pas publiés")).toBeTruthy();
  });

  it("présente simplement une fiche antérieure au récit : jamais la description longue ni la pile technique", async () => {
    const description =
      "<h2>Le projet</h2><p>Un portail qui présente le travail. Il qualifie les demandes.</p><h2>Sécurité</h2><p>Périmètre Cloudflare, Traefik, ROLE_SUPER_ADMIN.</p>";
    const { container } = await renderCase(
      project(
        {
          role: "Mon rôle — Architecte logiciel",
          objectives: ["Simplifier le parcours"],
          challenges: [
            { problem: "Pic de charge", solution: "Verrou optimiste" },
            { problem: "", solution: "Sans problème : ignoré" },
          ],
          results: [
            { label: "En production : Accessible publiquement", value: null },
            { label: "Délai", value: "6 semaines" },
          ],
          techStack: [{ name: "Infrastructure — Docker Compose, Traefik, Cloudflare" }],
        },
        description,
      ),
    );
    const text = container.textContent ?? "";
    // Zéro information de configuration : ni la description longue, ni la pile.
    expect(container.querySelector(".t-rich")).toBeNull();
    expect(container.querySelector(".chips")).toBeNull();
    expect(text).not.toMatch(/Cloudflare|Traefik|ROLE_|Docker/);
    // En tête : le premier paragraphe seulement (le titre « Le projet » est ignoré).
    expect(text).toContain("Un portail qui présente le travail. Il qualifie les demandes.");
    // Repères : rôle sans le préfixe répété, résultats chiffrés.
    expect([...container.querySelectorAll(".facts4 dd")].map((d) => d.textContent)).toEqual(["Architecte logiciel", "6 semaines"]);
    // Défis relevés : problème → solution, numérotés ; une entrée incomplète est ignorée.
    expect([...container.querySelectorAll(".defis b")].map((b) => b.textContent)).toEqual(["Pic de charge"]);
    expect(container.querySelector(".defis p")?.textContent).toBe("Verrou optimiste");
    // Ce que ça apporte : « Titre : texte » → titre en gras.
    expect(container.querySelector(".liv b")?.textContent).toBe("En production : ");
    expect(container.querySelector(".liv span")?.textContent).toBe("En production : Accessible publiquement");
    // Les objectifs bruts ne remplacent plus la démarche.
    expect(container.querySelector(".road")).toBeNull();
    expect(container.querySelector(".res")).toBeNull();
    // Note de confidentialité toujours présente.
    expect(container.querySelector(".disc")).not.toBeNull();
  });

  it("garde visibles les images au-delà des légendes", async () => {
    const media = [1, 2, 3].map((id) => ({ id, filePath: `/uploads/p${id}.png`, altText: `Image ${id}`, type: "image", mimeType: "image/png" }));
    const p = { ...project({ galleryCaptions: ["Légende 1"] }), media } as unknown as Project;
    const { container } = await renderCase(p);
    expect(container.querySelectorAll(".gal figure")).toHaveLength(3);
    expect([...container.querySelectorAll(".gal figcaption")].map((f) => f.textContent)).toEqual(["Légende 1"]);
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

describe("CaseStudy — utilitaires de texte", async () => {
  const { leadSentences, splitLabel, withoutLabelPrefix, firstParagraph } = await import("@/lib/projects/caseText");

  it("coupe à une fin de phrase, sinon à un mot entier avec « … »", () => {
    expect(leadSentences("Court.", 50)).toBe("Court.");
    expect(leadSentences("Première phrase ici. Deuxième phrase bien plus longue que la limite.", 40)).toBe("Première phrase ici.");
    expect(leadSentences("Un texte sans aucune ponctuation qui dépasse largement", 30)).toBe("Un texte sans aucune…");
  });

  it("sépare « Titre : texte » sans casser un texte ordinaire", () => {
    expect(splitLabel("En production : Accessible")).toEqual({ title: "En production", text: "Accessible" });
    expect(splitLabel("Bilingual: FR and EN")).toEqual({ title: "Bilingual", text: "FR and EN" });
    expect(splitLabel("Aucun deux-points ici")).toEqual({ title: null, text: "Aucun deux-points ici" });
  });

  it("retire seulement un préfixe qui répète le libellé", () => {
    expect(withoutLabelPrefix("Mon rôle — Architecte", "Mon rôle")).toBe("Architecte");
    expect(withoutLabelPrefix("Architecte", "Mon rôle")).toBe("Architecte");
    expect(withoutLabelPrefix("Mon rôle", "Mon rôle")).toBe("Mon rôle");
  });

  it("prend le premier paragraphe, entités décodées", () => {
    expect(firstParagraph("<h2>Titre</h2><p>L&#39;essentiel.</p><p>Suite</p>")).toBe("L'essentiel.");
    expect(firstParagraph("Texte brut")).toBe("Texte brut");
  });
});
