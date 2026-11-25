import { describe, expect, it } from "vitest";
import fr from "./fr.json";
import en from "./en.json";

/**
 * `landing.needs.items[].offer` doit être le slug RÉEL d'une offre publiée
 * (back-office) : sans correspondance, `SiteHome` fait retomber la carte sur
 * le catalogue général `/offres` au lieu de l'offre précise (silencieux, pas
 * d'erreur). Un slug change au back-office (ex. renommage pour le SEO) sans
 * que ce fichier suive — exactement ce qui s'est produit en production le
 * 2026-11-24 (slugs « whatsapp »/« architecture »/« audit »/« risques »
 * devenus « whatsapp-ai »/« architecture-logicielle »/« audit-securite »/
 * « gestion-des-risques »). Ce test ne connaît pas les slugs réels de
 * production (il ne doit pas dépendre d'un état externe) : il verrouille
 * seulement que les deux langues restent IDENTIQUES et dans le même ordre,
 * pour qu'une correction faite dans une langue ne soit jamais oubliée dans
 * l'autre.
 */
describe("landing.needs.items — cohérence fr/en", () => {
  it("chaque slug « offer » est identique entre le français et l'anglais, dans le même ordre", () => {
    const frOffers = fr.landing.needs.items.map((i) => i.offer);
    const enOffers = en.landing.needs.items.map((i) => i.offer);
    expect(enOffers).toEqual(frOffers);
  });

  it("aucun slug « offer » n'est vide", () => {
    for (const item of fr.landing.needs.items) {
      expect(item.offer.length).toBeGreaterThan(0);
    }
  });
});
