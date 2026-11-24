import { beforeEach, describe, expect, it, vi } from "vitest";

const apiFetch = vi.fn();
vi.mock("./client", async (importActual) => ({
  ...(await importActual<typeof import("./client")>()),
  apiFetch: (...args: unknown[]) => apiFetch(...args),
}));

const { getProjects } = await import("./projects");
const { getArticles } = await import("./articles");

const baseInfo = {
  role: null,
  objectives: [],
  techStack: [],
  challenges: [],
  results: [],
  repoUrl: null,
  coverImage: null,
  architectureDiagram: null,
};

function project(info: Record<string, unknown> | null) {
  return { id: 1, slug: "p", title: "Titre", description: "Desc", link: "", status: "in_progress", priority: null, billingType: null, progress: 0, media: [], info };
}

beforeEach(() => apiFetch.mockReset());

describe("getProjects — récit de l'étude de cas", () => {
  it("normalise une API antérieure à la migration (champs absents)", async () => {
    apiFetch.mockResolvedValue({ member: [project(baseInfo)] });
    const [p] = await getProjects("fr");
    expect(p.info).toMatchObject({
      result: null,
      context: null,
      stake: null,
      solution: null,
      brief: [],
      steps: [],
      deliverables: [],
      galleryCaptions: [],
      discreet: false,
      relatedOfferSlug: null,
    });
  });

  it("normalise les colonnes JSON nulles en listes vides", async () => {
    apiFetch.mockResolvedValue({ member: [project({ ...baseInfo, brief: null, steps: null, deliverables: null, galleryCaptions: null, discreet: true })] });
    const [p] = await getProjects("fr");
    expect(p.info?.brief).toEqual([]);
    expect(p.info?.galleryCaptions).toEqual([]);
    expect(p.info?.discreet).toBe(true);
  });

  it("choisit l'anglais quand il existe, sinon retombe sur le français", async () => {
    const info = {
      ...baseInfo,
      result: "Résultat",
      resultEn: "Result",
      context: "Contexte",
      contextEn: null,
      brief: [{ title: "Situation", text: "FR" }],
      briefEn: [{ title: "Situation", text: "EN" }],
      deliverables: ["Site"],
      deliverablesEn: [],
      relatedOfferSlug: "application",
    };
    apiFetch.mockResolvedValue({ member: [project(info)] });
    const [en] = await getProjects("en");
    expect(en.info?.result).toBe("Result");
    expect(en.info?.context).toBe("Contexte");
    expect(en.info?.brief).toEqual([{ title: "Situation", text: "EN" }]);
    expect(en.info?.deliverables).toEqual(["Site"]);
    expect(en.info?.relatedOfferSlug).toBe("application");
    // Les champs *En ne fuient pas dans l'objet localisé.
    expect(en.info).not.toHaveProperty("resultEn");
    const [fr] = await getProjects("fr");
    expect(fr.info?.result).toBe("Résultat");
  });
});

describe("getArticles — chapô, offre liée, date", () => {
  const article = { id: 1, slug: "a", title: "T", content: "<p>x</p>", tags: [], media: [] };

  it("expose chapô localisé, offre liée et date", async () => {
    apiFetch.mockResolvedValue({
      member: [{ ...article, summary: "Chapô", summaryEn: "Summary", relatedOfferSlug: "audit", publishedAt: "2026-11-24T11:37:37+00:00" }],
    });
    const [fr] = await getArticles("fr");
    expect(fr).toMatchObject({ summary: "Chapô", relatedOfferSlug: "audit", publishedAt: "2026-11-24T11:37:37+00:00" });
    const [en] = await getArticles("en");
    expect(en.summary).toBe("Summary");
    expect(en).not.toHaveProperty("summaryEn");
  });

  it("met null quand l'API ne les renvoie pas", async () => {
    apiFetch.mockResolvedValue({ member: [article] });
    const [a] = await getArticles("fr");
    expect(a).toMatchObject({ summary: null, relatedOfferSlug: null, publishedAt: null });
  });
});
