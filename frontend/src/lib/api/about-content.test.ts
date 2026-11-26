import { beforeEach, describe, expect, it, vi } from "vitest";

const apiFetch = vi.fn();
vi.mock("./client", async (importActual) => ({
  ...(await importActual<typeof import("./client")>()),
  apiFetch: (...args: unknown[]) => apiFetch(...args),
}));

const { getAboutContent } = await import("./about-content");

/** Ligne minimale telle qu'un backend ANTÉRIEUR à la migration R5 la renvoie. */
function legacyRow(): Record<string, unknown> {
  const text = (k: string) => [k, `${k} fr`];
  return {
    ...Object.fromEntries(
      [
        "heroTitle", "heroTitleAccent", "heroSub", "profileName", "profileRole", "profileAvailability",
        "bioTitle", "bioP1", "bioP2", "visionLede", "visionTodayText", "visionTomorrowText", "why1Title", "why1Desc", "why2Title",
        "why2Desc", "why3Title", "why3Desc", "why4Title", "why4Desc", "ctaTitle", "ctaSub",
      ].map(text),
    ),
  };
}

describe("getAboutContent — champs de la refonte R5", () => {
  beforeEach(() => apiFetch.mockReset());

  /** Frontend déployé avant le backend : la page ne doit pas casser. */
  it("backend sans les champs R5 : textes vides et aucun repère", async () => {
    apiFetch.mockResolvedValue({ member: [legacyRow()] });

    const c = await getAboutContent("fr");

    expect(c?.visionPillar).toBe("");
    expect(c?.learningText).toBe("");
    expect(c?.journeyStats).toEqual([]);
  });

  it("ne garde que des repères {value, label} de chaînes non vides", async () => {
    apiFetch.mockResolvedValue({
      member: [
        {
          ...legacyRow(),
          journeyStats: [
            { value: "Depuis 2016", label: "dans le numérique" },
            { value: "", label: "vide" },
            { value: "2020", label: "   " },
            { value: 2026, label: "nombre" },
            { label: "sans valeur" },
            "chaîne",
            null,
            [["tableau"]],
            { value: "2026", label: "fondation", extra: "<script>" },
          ],
        },
      ],
    });

    const c = await getAboutContent("fr");

    expect(c?.journeyStats).toEqual([
      { value: "Depuis 2016", label: "dans le numérique" },
      { value: "2026", label: "fondation" },
    ]);
  });

  it("journeyStats qui n'est pas une liste : aucun repère, pas d'exception", async () => {
    apiFetch.mockResolvedValue({ member: [{ ...legacyRow(), journeyStats: { value: "x", label: "y" } }] });

    expect((await getAboutContent("fr"))?.journeyStats).toEqual([]);
  });

  it("anglais : version anglaise si elle a au moins un repère valide, sinon le français", async () => {
    const fr = [{ value: "Depuis 2016", label: "dans le numérique" }];
    apiFetch.mockResolvedValue({
      member: [{ ...legacyRow(), journeyStats: fr, journeyStatsEn: [{ value: "Since 2016", label: "in digital" }], missionPillar: "Mission", missionPillarEn: "Mission EN" }],
    });
    const en = await getAboutContent("en");
    expect(en?.journeyStats).toEqual([{ value: "Since 2016", label: "in digital" }]);
    expect(en?.missionPillar).toBe("Mission EN");

    apiFetch.mockResolvedValue({ member: [{ ...legacyRow(), journeyStats: fr, journeyStatsEn: [{ value: "", label: "" }], missionPillar: "Mission" }] });
    const fallback = await getAboutContent("en");
    expect(fallback?.journeyStats).toEqual(fr);
    expect(fallback?.missionPillar).toBe("Mission");
  });

  it("titres de sections du back-office : français, anglais avec repli, vide si absent", async () => {
    apiFetch.mockResolvedValue({
      member: [{ ...legacyRow(), skillsTitle: "Quatre domaines", skillsTitleEn: "Four areas", pathLede: "Des maths à l'entreprise", pathLedeEn: "" }],
    });

    const fr = await getAboutContent("fr");
    expect(fr?.skillsTitle).toBe("Quatre domaines");
    const en = await getAboutContent("en");
    expect(en?.skillsTitle).toBe("Four areas");
    expect(en?.pathLede).toBe("Des maths à l'entreprise");
    expect(en?.projectsTitle).toBe("");
  });
});
