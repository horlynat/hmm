import { beforeEach, describe, expect, it, vi } from "vitest";

const apiFetch = vi.fn();
vi.mock("./client", async (importActual) => ({
  ...(await importActual<typeof import("./client")>()),
  apiFetch: (...args: unknown[]) => apiFetch(...args),
}));

const { getOffers, heroOffers } = await import("./offers");

/** Offre telle que l'API la renvoie réellement (champs NULL omis). */
function raw(slug: string, position: number, heroPosition?: number) {
  return {
    id: position,
    slug,
    position,
    ...(heroPosition === undefined ? {} : { heroPosition }),
    featured: false,
    category: "dev",
    icon: "web",
    previewType: "site",
    situation: "clients",
    price: 1000,
    priceOnce: true,
    title: slug,
    shortTitle: slug,
    pain: "",
    headline: "",
    leadText: "",
    cardText: "",
    audience: "",
    clientNeeds: "",
    delay: "",
    includes: [],
    beforeItems: [],
    afterItems: [],
    calendar: [],
    pack: [],
  };
}

describe("offres de l'API", () => {
  beforeEach(() => apiFetch.mockReset());

  /**
   * API Platform omet les champs NULL (`skip_null_values`) : une offre hors
   * carrousel arrive SANS clé heroPosition. Elle ne doit pas entrer dans le
   * carrousel, et l'ordre de celles qui y sont doit suivre heroPosition.
   */
  it("une offre sans clé heroPosition reste hors du carrousel", async () => {
    apiFetch.mockResolvedValue({ member: [raw("a", 1, 2), raw("b", 2), raw("c", 3, 1), raw("d", 4)] });

    const offers = await getOffers("fr");

    expect(offers.map((o) => o.heroPosition)).toEqual([2, null, 1, null]);
    expect(offers.every((o) => o.proofProjectSlug === null)).toBe(true);
    expect(heroOffers(offers).map((o) => o.slug)).toEqual(["c", "a"]);
  });

  it("API en panne : aucune offre, pas d'exception", async () => {
    apiFetch.mockResolvedValue(null);

    expect(await getOffers("fr")).toEqual([]);
  });
});
