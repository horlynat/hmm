import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getOfferBySlug, getOffers, getOfferSlugs } from "@/lib/api/offers";
import { getProjectBySlug } from "@/lib/api/projects";
import { resolveOgLocale, SITE_URL } from "@/lib/metadata";
import { siteConfig } from "@/config/site";
import { getPathname } from "@/i18n/navigation";
import { routing } from "@/i18n/routing";
import { OfferDetail } from "@/components/offers-proto/OfferDetail";

export const dynamic = "force-static";

// Pré-génère une page par offre et par locale au build — même liste d'offres
// quelle que soit la langue (cf. realisations/[slug]/page.tsx).
export async function generateStaticParams() {
  const slugs = await getOfferSlugs();
  return slugs.map((slug) => ({ slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string; locale: string }>;
}): Promise<Metadata> {
  const { slug, locale } = await params;
  const offer = await getOfferBySlug(slug, locale);
  if (!offer) return {};

  const image = `${SITE_URL}/${locale}/opengraph-image`;
  // Canonical et hreflang propres à la fiche : sans eux, la page hérite de
  // ceux du layout racine (l'accueil) et les moteurs la traitent comme un
  // doublon de l'accueil, donc ne l'indexent pas.
  const href = { pathname: "/offres/[slug]" as const, params: { slug: offer.slug } };
  const url = `${SITE_URL}${getPathname({ locale, href })}`;
  const languages = Object.fromEntries(
    routing.locales.map((l) => [l, `${SITE_URL}${getPathname({ locale: l, href })}`]),
  );
  return {
    title: offer.title,
    description: offer.cardText,
    alternates: { canonical: url, languages },
    openGraph: {
      title: offer.title,
      description: offer.cardText,
      url,
      type: "website",
      siteName: siteConfig.name,
      locale: resolveOgLocale(locale),
      images: [image],
    },
    twitter: {
      card: "summary_large_image",
      title: offer.title,
      description: offer.cardText,
      images: [image],
    },
  };
}

export default async function OfferDetailPage({ params }: { params: Promise<{ slug: string; locale: string }> }) {
  const { slug, locale } = await params;
  const [offer, offers] = await Promise.all([getOfferBySlug(slug, locale), getOffers(locale)]);
  if (!offer) notFound();
  // Réalisation liée (back-office) : affichée seulement si elle est publiée.
  const proof = offer.proofProjectSlug ? await getProjectBySlug(offer.proofProjectSlug, locale) : null;
  return <OfferDetail locale={locale} offer={offer} offers={offers} proof={proof} />;
}
