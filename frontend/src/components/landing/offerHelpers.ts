import type { Offer } from "@/lib/types";
import { PROTO_ICONS, type ProtoIconName } from "@/components/proto/assets";

/** Icône d'offre saisie au back-office -> icône du prototype (repli : étincelle). */
export function offerIcon(icon: string): ProtoIconName {
  return Object.hasOwn(PROTO_ICONS, icon) ? (icon as ProtoIconName) : "spark";
}

const SITUATIONS: Offer["situation"][] = ["clients", "appli", "secu", "assur"];

/** Accueil : une offre par situation (la vedette d'abord, sinon la première par ordre d'affichage). */
export function pickHomeOffers(offers: Offer[]): Offer[] {
  const picked = SITUATIONS.map(
    (s) => offers.find((o) => o.situation === s && o.featured) ?? offers.find((o) => o.situation === s),
  ).filter((o): o is Offer => o !== undefined);
  return picked.sort((a, b) => Number(b.featured) - Number(a.featured));
}
