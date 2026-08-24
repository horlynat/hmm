import type { Metadata } from "next";
import { getPathname } from "@/i18n/navigation";
import { routing } from "@/i18n/routing";
import { siteConfig, type NavHref } from "@/config/site";

export const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL ?? "https://horlynat.com";

// Locale OpenGraph (format IETF étendu attendu par og:locale) — pas la même
// chose que la locale next-intl ("fr", "en") utilisée dans les URLs.
const OG_LOCALE: Record<string, string> = { fr: "fr_CG", en: "en_US" };

/** Exporté pour les pages qui construisent leur `openGraph` à la main (ex. articles/projets avec image dynamique). */
export function resolveOgLocale(locale: string): string {
  return OG_LOCALE[locale] ?? locale;
}

interface PageMetadataInput {
  locale: string;
  /**
   * Chemin non localisé, ex. "/a-propos" — limité à `NavHref` (routes
   * statiques, sans segment dynamique) : les pages de détail (`/blog/[slug]`,
   * `/realisations/[slug]`) construisent leur propre OpenGraph avec une
   * image par contenu, pas via ce helper.
   */
  pathname: NavHref;
  title: string;
  description: string;
}

/**
 * Métadonnées communes à toutes les pages marketing : canonical, hreflang,
 * OpenGraph et Twitter Card, image de partage incluse.
 *
 * L'image vient de `[locale]/opengraph-image.tsx` (générée par Next.js) —
 * mais référencée ici explicitement plutôt que laissée au rattachement
 * automatique de Next.js : ce rattachement ne se produit QUE si la route ne
 * définit pas son propre `openGraph`, or c'est justement ce que fait cette
 * fonction pour chaque page (title/description propres) ; sans cette ligne,
 * `og:image` disparaît silencieusement dès qu'une page a son propre
 * `openGraph` (vérifié : /opengraph-image répond 200, mais n'apparaissait
 * dans aucune page qui appelle buildPageMetadata avant cet ajout).
 */
export function buildPageMetadata({ locale, pathname, title, description }: PageMetadataInput): Metadata {
  const url = `${SITE_URL}${getPathname({ locale, href: pathname })}`;
  const languages = Object.fromEntries(
    routing.locales.map((l) => [l, `${SITE_URL}${getPathname({ locale: l, href: pathname })}`]),
  );
  const image = `${SITE_URL}/${locale}/opengraph-image`;

  return {
    title,
    description,
    alternates: { canonical: url, languages },
    openGraph: {
      title,
      description,
      url,
      siteName: siteConfig.name,
      locale: OG_LOCALE[locale] ?? locale,
      type: "website",
      images: [{ url: image, width: 1200, height: 630, alt: siteConfig.name }],
    },
    twitter: {
      card: "summary_large_image",
      title,
      description,
      images: [image],
    },
  };
}
