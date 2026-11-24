import Image from "next/image";
import type { Offer } from "@/lib/types";
import { PROTO_ICONS, type ProtoIconName } from "@/components/proto/assets";
import { OfferVisual } from "@/components/proto/ProtoSvg";
import { getMediaUrl } from "@/lib/media";

/** Icône d'offre saisie au back-office -> icône du prototype (repli : étincelle). */
export function offerIcon(icon: string): ProtoIconName {
  return Object.hasOwn(PROTO_ICONS, icon) ? (icon as ProtoIconName) : "spark";
}

/**
 * Visuel d'une offre : la vraie photo du back-office (`Offer.coverImage`) si
 * elle existe, sinon l'illustration dessinée du prototype (`OfferVisual`,
 * inchangée) — jamais les deux. `visualSlug` permet de garder exactement la
 * même clé d'illustration qu'avant ce champ (ex. repli "automatisation" sur
 * `previewType === "flow"` dans la fiche détaillée) quand la photo est absente.
 */
export function OfferVisualOrPhoto({ offer, visualSlug, sizes }: { offer: Offer; visualSlug?: string; sizes: string }) {
  if (offer.coverImage) {
    return (
      <Image
        src={getMediaUrl(offer.coverImage.filePath)}
        alt={offer.coverImage.altText ?? offer.title}
        fill
        sizes={sizes}
        style={{ objectFit: "cover" }}
      />
    );
  }
  return <OfferVisual slug={visualSlug ?? offer.slug} previewType={offer.previewType} />;
}

/** Acompte des conditions de prestation : 60 % à la commande, 40 % à la livraison. */
export const DEPOSIT_RATE = 0.6;

/** Échéancier d'un prix (FCFA entiers) : acompte arrondi, le solde complète exactement le prix. */
export function splitPayment(price: number): { deposit: number; balance: number } {
  const deposit = Math.round(price * DEPOSIT_RATE);
  return { deposit, balance: price - deposit };
}

const SITUATIONS: Offer["situation"][] = ["clients", "appli", "secu", "assur"];

/** Accueil : une offre par situation (la vedette d'abord, sinon la première par ordre d'affichage). */
export function pickHomeOffers(offers: Offer[]): Offer[] {
  const picked = SITUATIONS.map(
    (s) => offers.find((o) => o.situation === s && o.featured) ?? offers.find((o) => o.situation === s),
  ).filter((o): o is Offer => o !== undefined);
  return picked.sort((a, b) => Number(b.featured) - Number(a.featured));
}
