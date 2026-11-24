import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getArticleBySlug, getArticles, getArticleSlugs } from "@/lib/api/articles";
import { getArticleExcerpt } from "@/lib/sanitize";
import { getMediaUrl } from "@/lib/media";
import { jsonLdScript } from "@/lib/json-ld";
import { siteConfig } from "@/config/site";
import { resolveOgLocale, SITE_URL } from "@/lib/metadata";
import { getPathname } from "@/i18n/navigation";
import { routing } from "@/i18n/routing";
import { ArticleDetail } from "@/components/blog-proto/ArticleDetail";

export const dynamic = "force-static";

// Pré-génère une page par article et par locale au build (cf. offres/[slug]).
export async function generateStaticParams() {
  const slugs = await getArticleSlugs();
  return slugs.map((slug) => ({ slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string; locale: string }>;
}): Promise<Metadata> {
  const { slug, locale } = await params;
  const article = await getArticleBySlug(slug, locale);
  if (!article) return {};

  // Chapô du back-office (rédigé pour ça) ; à défaut, extrait du contenu.
  const description = article.summary ?? getArticleExcerpt(article.content);
  const image = article.media[0] ? getMediaUrl(article.media[0].filePath) : `${SITE_URL}/${locale}/opengraph-image`;

  // Canonical et hreflang propres à l'article (cf. offres/[slug]).
  const href = { pathname: "/blog/[slug]" as const, params: { slug: article.slug } };
  const url = `${SITE_URL}${getPathname({ locale, href })}`;
  const languages = Object.fromEntries(
    routing.locales.map((l) => [l, `${SITE_URL}${getPathname({ locale: l, href })}`]),
  );

  return {
    title: article.title,
    description,
    alternates: { canonical: url, languages },
    openGraph: {
      title: article.title,
      description,
      url,
      type: "article",
      siteName: siteConfig.name,
      locale: resolveOgLocale(locale),
      images: [image],
    },
    twitter: {
      card: "summary_large_image",
      title: article.title,
      description,
      images: [image],
    },
  };
}

export default async function ArticleDetailPage({ params }: { params: Promise<{ slug: string; locale: string }> }) {
  const { slug, locale } = await params;
  const [article, articles] = await Promise.all([getArticleBySlug(slug, locale), getArticles(locale)]);
  if (!article) notFound();

  const image = article.media[0] ? getMediaUrl(article.media[0].filePath) : undefined;
  const url = `${SITE_URL}${getPathname({ locale, href: { pathname: "/blog/[slug]", params: { slug: article.slug } } })}`;
  // Date réelle du back-office ; une valeur illisible est ignorée (aucune date inventée).
  const publishedAt = article.publishedAt && !Number.isNaN(Date.parse(article.publishedAt)) ? article.publishedAt : null;
  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "Article",
    headline: article.title,
    description: article.summary ?? getArticleExcerpt(article.content),
    image: image ? [image] : undefined,
    datePublished: publishedAt ?? undefined,
    author: { "@type": "Person", name: siteConfig.name },
    keywords: article.tags.length ? article.tags.map((t) => t.name).join(", ") : undefined,
    mainEntityOfPage: { "@type": "WebPage", "@id": url },
    inLanguage: locale,
  };

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: jsonLdScript(jsonLd) }} />
      <ArticleDetail locale={locale} article={article} articles={articles} publishedAt={publishedAt} />
    </>
  );
}
