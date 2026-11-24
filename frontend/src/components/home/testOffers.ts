import type { Offer } from "@/lib/types";

/** Offre de test complète, surchargeable champ par champ. */
export function makeOffer(overrides: Partial<Offer> = {}): Offer {
  return {
    id: 1,
    slug: "whatsapp",
    position: 10,
    heroPosition: 1,
    featured: false,
    category: "dev",
    icon: "shop",
    previewType: "chat",
    coverImage: null,
    situation: "clients",
    proofProjectSlug: null,
    price: 199999,
    priceOnce: true,
    title: "Assistant IA WhatsApp",
    shortTitle: "Assistant WhatsApp",
    pain: "Vos clients écrivent le soir ?",
    headline: "Votre assistant WhatsApp IA en 21 jours.",
    leadText: "Il répond à vos clients jour et nuit.",
    cardText: "Une réponse immédiate, même la nuit.",
    audience: "Commerces",
    clientNeeds: "Vos produits et vos prix.",
    delay: "21 jours",
    includes: ["Répond 24 h sur 24", "Prend les commandes"],
    beforeItems: [],
    afterItems: [],
    calendar: [{ day: "J1", text: "Échange" }],
    pack: [],
    ...overrides,
  };
}
