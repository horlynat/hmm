import { beforeEach, describe, expect, it, vi } from "vitest";

const getOfferSlugs = vi.fn();
vi.mock("@/lib/api/offers", () => ({ getOfferSlugs: () => getOfferSlugs() }));
vi.mock("@/lib/api/projects", () => ({ getProjects: async () => [] }));
vi.mock("@/lib/api/articles", () => ({ getArticles: async () => [] }));

const { default: sitemap } = await import("./sitemap");

const SITE = process.env.NEXT_PUBLIC_SITE_URL ?? "https://horlynat.com";

describe("sitemap", () => {
  beforeEach(() => getOfferSlugs.mockReset());

  /**
   * R4 : le catalogue et chaque fiche d'offre doivent être déclarés aux
   * moteurs, dans les deux langues, avec leurs URL localisées.
   */
  it("déclare le catalogue et chaque fiche d'offre, en fr et en en", async () => {
    getOfferSlugs.mockResolvedValue(["whatsapp", "audit"]);
    const urls = (await sitemap()).map((e) => e.url);

    expect(urls).toEqual(
      expect.arrayContaining([
        `${SITE}/fr/offres`,
        `${SITE}/en/offers`,
        `${SITE}/fr/offres/whatsapp`,
        `${SITE}/en/offers/whatsapp`,
        `${SITE}/fr/offres/audit`,
        `${SITE}/en/offers/audit`,
      ]),
    );
  });

  it("API des offres en panne : le sitemap reste généré, sans fiche d'offre", async () => {
    getOfferSlugs.mockResolvedValue([]);
    const urls = (await sitemap()).map((e) => e.url);

    expect(urls).toContain(`${SITE}/fr/offres`);
    expect(urls.some((u) => /\/(offres|offers)\/./.test(u))).toBe(false);
  });
});
