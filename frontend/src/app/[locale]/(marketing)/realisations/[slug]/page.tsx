import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getProjectBySlug, getProjects, getProjectSlugs } from "@/lib/api/projects";
import { getOffers } from "@/lib/api/offers";
import { getMediaUrl } from "@/lib/media";
import { jsonLdScript } from "@/lib/json-ld";
import { siteConfig } from "@/config/site";
import { resolveOgLocale, SITE_URL } from "@/lib/metadata";
import { getArticleExcerpt } from "@/lib/sanitize";
import { sortProjectsForDisplay } from "@/lib/projects/sortForDisplay";
import { CaseStudy } from "@/components/cases/CaseStudy";
import { getPathname } from "@/i18n/navigation";
import { routing } from "@/i18n/routing";

export const dynamic = "force-static";

// Pré-génère une page par projet et par locale au build (cf. generateStaticParams
// "top-down" de [locale]/layout.tsx qui fournit `locale` — même liste de projets
// quelle que soit la langue, `locale` n'a donc pas besoin d'être utilisé ici).
export async function generateStaticParams() {
  const slugs = await getProjectSlugs();
  return slugs.map((slug) => ({ slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string; locale: string }>;
}): Promise<Metadata> {
  const { slug, locale } = await params;
  const project = await getProjectBySlug(slug, locale);

  if (!project) return {};

  // Sans image de couverture, repli sur l'image générée par
  // `[locale]/opengraph-image.tsx` — référencée explicitement (Next.js ne la
  // rattache pas automatiquement quand la route définit son propre
  // `openGraph`, cf. commentaire dans lib/metadata.ts).
  const image = project.info?.coverImage
    ? getMediaUrl(project.info.coverImage.filePath)
    : `${SITE_URL}/${locale}/opengraph-image`;

  // `description` peut désormais contenir du HTML structuré (éditeur riche
  // admin, cf. rich_text_controller.js côté backend) — extrait un résumé
  // texte brut pour les meta description / Open Graph / Twitter, qui
  // n'acceptent pas de balisage.
  const excerpt = getArticleExcerpt(project.description);

  // Canonical et hreflang propres à la fiche : sans eux, la page hérite de
  // ceux du layout racine (l'accueil) et les moteurs la traitent comme un
  // doublon de l'accueil, donc ne l'indexent pas (cf. offres/[slug]).
  const href = { pathname: "/realisations/[slug]" as const, params: { slug: project.slug } };
  const url = `${SITE_URL}${getPathname({ locale, href })}`;
  const languages = Object.fromEntries(
    routing.locales.map((l) => [l, `${SITE_URL}${getPathname({ locale: l, href })}`]),
  );

  return {
    title: project.title,
    description: excerpt,
    alternates: { canonical: url, languages },
    openGraph: {
      title: project.title,
      description: excerpt,
      url,
      type: "article",
      siteName: siteConfig.name,
      locale: resolveOgLocale(locale),
      images: [image],
    },
    twitter: {
      card: "summary_large_image",
      title: project.title,
      description: excerpt,
      images: [image],
    },
  };
}

export default async function ProjectDetailPage({ params }: { params: Promise<{ slug: string; locale: string }> }) {
  const { slug, locale } = await params;
  const [project, projects, offers] = await Promise.all([
    getProjectBySlug(slug, locale),
    getProjects(locale),
    getOffers(locale),
  ]);
  if (!project) notFound();

  // Projet suivant dans l'ordre d'affichage (bouclé), masqué s'il est seul.
  const ordered = sortProjectsForDisplay(projects);
  const index = ordered.findIndex((p) => p.slug === project.slug);
  const next = ordered.length > 1 ? ordered[(index + 1) % ordered.length] : null;
  // Offre dont cette réalisation est la preuve (back-office › Offres › réalisation liée).
  // Offre choisie au back-office pour cette étude de cas ; à défaut, l'offre
  // qui cite ce projet en preuve. Seules les offres publiées sont listées.
  const offer =
    (project.info?.relatedOfferSlug ? offers.find((o) => o.slug === project.info?.relatedOfferSlug) : undefined) ??
    offers.find((o) => o.proofProjectSlug === project.slug) ??
    null;

  const image = project.info?.coverImage ? getMediaUrl(project.info.coverImage.filePath) : undefined;
  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "CreativeWork",
    name: project.title,
    description: getArticleExcerpt(project.description),
    image: image ? [image] : undefined,
    author: { "@type": "Person", name: siteConfig.name },
    keywords: project.info?.techStack.length ? project.info.techStack.map((s) => s.name).join(", ") : undefined,
    url: project.link || undefined,
  };

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: jsonLdScript(jsonLd) }} />
      <CaseStudy locale={locale} project={project} next={next} offer={offer} />
    </>
  );
}
