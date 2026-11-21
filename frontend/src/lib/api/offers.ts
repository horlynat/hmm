import "server-only";
import { apiFetch, extractCollection, pickLocalized, pickLocalizedList } from "./client";
import type { Offer, OfferCalendarStep, OfferPackBlock } from "@/lib/types";

// Contenu commercial, modifié occasionnellement depuis le back-office — la
// fraîcheur réelle vient du webhook /api/revalidate (tag "offers", déclenché
// par App\EventSubscriber\FrontendRevalidationSubscriber) ; ce délai n'est
// qu'un filet de sécurité si ce webhook venait à échouer.
const OFFERS_REVALIDATE_SECONDS = 60 * 60 * 24;

/**
 * API Platform omet les champs NULL de la réponse (`skip_null_values`, actif
 * par défaut) : une offre hors carrousel arrive SANS clé `heroPosition`, pas
 * avec `null`. Les champs nullables sont donc optionnels ici et normalisés en
 * `null` dans `localizeOffer` — sinon `undefined !== null` ferait entrer
 * l'offre dans le carrousel.
 */
interface RawOffer
  extends Omit<Offer, "heroPosition" | "proofProjectSlug" | "includes" | "beforeItems" | "afterItems" | "calendar" | "pack"> {
  heroPosition?: number | null;
  proofProjectSlug?: string | null;
  titleEn?: string | null;
  shortTitleEn?: string | null;
  painEn?: string | null;
  headlineEn?: string | null;
  leadTextEn?: string | null;
  cardTextEn?: string | null;
  audienceEn?: string | null;
  clientNeedsEn?: string | null;
  delayEn?: string | null;
  includes: string[];
  includesEn?: string[] | null;
  beforeItems: string[];
  beforeItemsEn?: string[] | null;
  afterItems: string[];
  afterItemsEn?: string[] | null;
  calendar: OfferCalendarStep[];
  calendarEn?: OfferCalendarStep[] | null;
  pack: OfferPackBlock[];
  packEn?: OfferPackBlock[] | null;
}

function localizeOffer(raw: RawOffer, locale: string): Offer {
  const {
    titleEn, shortTitleEn, painEn, headlineEn, leadTextEn, cardTextEn, audienceEn, clientNeedsEn, delayEn,
    includesEn, beforeItemsEn, afterItemsEn, calendarEn, packEn,
    ...rest
  } = raw;

  return {
    ...rest,
    heroPosition: typeof raw.heroPosition === "number" ? raw.heroPosition : null,
    proofProjectSlug: raw.proofProjectSlug ?? null,
    title: pickLocalized(raw.title, titleEn, locale),
    shortTitle: pickLocalized(raw.shortTitle, shortTitleEn, locale),
    pain: pickLocalized(raw.pain, painEn, locale),
    headline: pickLocalized(raw.headline, headlineEn, locale),
    leadText: pickLocalized(raw.leadText, leadTextEn, locale),
    cardText: pickLocalized(raw.cardText, cardTextEn, locale),
    audience: pickLocalized(raw.audience, audienceEn, locale),
    clientNeeds: pickLocalized(raw.clientNeeds, clientNeedsEn, locale),
    delay: pickLocalized(raw.delay, delayEn, locale),
    includes: pickLocalizedList(raw.includes, includesEn, locale),
    beforeItems: pickLocalizedList(raw.beforeItems, beforeItemsEn, locale),
    afterItems: pickLocalizedList(raw.afterItems, afterItemsEn, locale),
    calendar: pickLocalizedList(raw.calendar, calendarEn, locale),
    pack: pickLocalizedList(raw.pack, packEn, locale),
  };
}

/**
 * Offres publiées, triées par position (déjà l'ordre renvoyé par l'API —
 * OfferApiResource::order — mais retrié ici par robustesse, au cas où la
 * réponse serait mise en cache dans un ordre différent).
 *
 * `locale` est un paramètre explicite — cf. commentaire dans projects.ts
 * (bug de mémoïsation de `getLocale()` pendant `next build`).
 */
export async function getOffers(locale: string): Promise<Offer[]> {
  const payload = await apiFetch<unknown>("/offers", {
    tags: ["offers"],
    revalidate: OFFERS_REVALIDATE_SECONDS,
  });

  return extractCollection<RawOffer>(payload)
    .map((raw) => localizeOffer(raw, locale))
    .sort((a, b) => a.position - b.position);
}

/** Offres à afficher dans le carrousel du hero, triées par heroPosition. */
export function heroOffers(offers: Offer[]): Offer[] {
  return offers
    .filter((offer): offer is Offer & { heroPosition: number } => typeof offer.heroPosition === "number")
    .sort((a, b) => a.heroPosition - b.heroPosition);
}

/**
 * Offre unique par slug (page de détail `/offres/[slug]`) — filtre sur la
 * collection plutôt qu'un GET direct : comme `Project`
 * (App\ApiResource\ProjectApiResource), `OfferApiResource` n'expose qu'une
 * seule opération GetCollection, l'identifiant de la ressource est `id`,
 * aucune route /offers/{slug} n'existe côté backend.
 */
export async function getOfferBySlug(slug: string, locale: string): Promise<Offer | null> {
  const offers = await getOffers(locale);
  return offers.find((offer) => offer.slug === slug) ?? null;
}

/**
 * Slugs uniquement, indépendant de la locale — pour `generateStaticParams`
 * (exécuté au build, sans contexte de requête). Mêmes slugs quelle que soit
 * la langue.
 */
export async function getOfferSlugs(): Promise<string[]> {
  const payload = await apiFetch<unknown>("/offers", {
    tags: ["offers"],
    revalidate: OFFERS_REVALIDATE_SECONDS,
  });
  return extractCollection<{ slug: string }>(payload).map((offer) => offer.slug);
}
