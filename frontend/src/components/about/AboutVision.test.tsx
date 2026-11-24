import { describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import { AboutVision } from "./AboutVision";
import type { AboutContent } from "@/lib/types";

vi.mock("next-intl/server", () => ({
  getTranslations: async () => (key: string) => key,
}));

function content(overrides: Partial<AboutContent> = {}): AboutContent {
  return {
    heroEyebrow: "", heroTitle: "", heroTitleAccent: "", heroSub: "", profileName: "", profileRole: "",
    profileAvailability: "", profileAlso: "", profileLocation: "", profileWorkMode: "", profileLanguages: "",
    profileImagePath: null, bioTitle: "", bioP1: "", bioP2: "", bioP3: "",
    visionTitle: "Ma vision", visionLede: "Penser au risque avant de construire.",
    visionTodayText: "Aujourd'hui texte", visionTomorrowText: "Long terme texte",
    why1Title: "", why1Desc: "", why2Title: "", why2Desc: "", why3Title: "", why3Desc: "", why4Title: "", why4Desc: "",
    beyondLanguages: [], beyondInterests: [], ctaTitle: "", ctaSub: "",
    visionPillar: "Vision texte", missionPillar: "Mission texte", ambitionPillar: "Ambition texte",
    horizonMidText: "Trois ans texte", learningText: "", journeyStats: [],
    ...overrides,
  };
}

async function renderVision(c: AboutContent) {
  return render(await AboutVision({ locale: "fr", content: c }));
}

describe("AboutVision", () => {
  it("affiche la citation, les trois cartes et les trois horizons dans l'ordre", async () => {
    await renderVision(content());

    expect(screen.getByRole("heading", { level: 2, name: "Ma vision" })).toBeInTheDocument();
    expect(screen.getByText("Penser au risque avant de construire.")).toBeInTheDocument();
    expect(screen.getAllByRole("heading", { level: 3 }).map((h) => h.textContent)).toEqual([
      "pillars.vision",
      "pillars.mission",
      "pillars.ambition",
    ]);
    expect(screen.getAllByRole("listitem").map((li) => li.textContent)).toEqual([
      "horizons.todayAujourd'hui texte",
      "horizons.midTermTrois ans texte",
      "horizons.longTermLong terme texte",
    ]);
  });

  it("masque une carte ou un horizon vide (blocs facultatifs du back-office)", async () => {
    await renderVision(content({ missionPillar: "   ", horizonMidText: "" }));

    expect(screen.queryByText("pillars.mission")).toBeNull();
    expect(screen.getAllByRole("heading", { level: 3 })).toHaveLength(2);
    expect(screen.queryByText("horizons.midTerm")).toBeNull();
    expect(screen.getAllByRole("listitem")).toHaveLength(2);
  });

  it("aucune carte ni horizon renseigné : les rangées disparaissent", async () => {
    await renderVision(
      content({ visionPillar: "", missionPillar: "", ambitionPillar: "", visionTodayText: "", horizonMidText: "", visionTomorrowText: "", visionLede: "" }),
    );

    expect(screen.queryAllByRole("heading", { level: 3 })).toHaveLength(0);
    expect(screen.queryByRole("list")).toBeNull();
    expect(screen.queryByText("horizons.title")).toBeNull();
  });

  /** Contenu saisi en back-office : rendu comme du texte, jamais comme du HTML. */
  it("n'interprète jamais le contenu comme du HTML", async () => {
    const evil = '<img src=x onerror="window.__pwned=1"><script>window.__pwned=1</script>';
    const { container } = await renderVision(content({ visionLede: evil, visionPillar: evil, horizonMidText: evil }));

    expect(container.querySelector("img, script")).toBeNull();
    expect(screen.getAllByText(evil, { exact: false }).length).toBeGreaterThan(0);
    expect((window as unknown as { __pwned?: number }).__pwned).toBeUndefined();
  });
});
