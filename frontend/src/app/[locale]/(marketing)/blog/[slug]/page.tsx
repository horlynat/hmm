import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { Badge, ButtonLink } from "@/components/ui";
import { getArticleBySlug } from "@/lib/api/articles";
import { sanitizeArticleHtml, getArticleExcerpt } from "@/lib/sanitize";
import { getMediaUrl } from "@/lib/media";
import { jsonLdScript } from "@/lib/json-ld";
import { siteConfig } from "@/config/site";
import { resolveOgLocale, SITE_URL } from "@/lib/metadata";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string; locale: string }>;
}): Promise<Metadata> {
  const { slug, locale } = await params;
  const article = await getArticleBySlug(slug, locale);

  if (!article) return {};

  const description = getArticleExcerpt(article.content);
  // Sans image propre à l'article, repli sur l'image générée par
  // `[locale]/opengraph-image.tsx` — référencée explicitement, car Next.js
  // ne la rattache PAS automatiquement dès qu'une route définit son propre
  // `openGraph` (vérifié en dev : le rattachement implicite ne se produit
  // jamais dans ce cas, cf. commentaire dans lib/metadata.ts).
  const image = article.media[0]
    ? getMediaUrl(article.media[0].filePath)
    : `${SITE_URL}/${locale}/opengraph-image`;

  return {
    title: article.title,
    description,
    openGraph: {
      title: article.title,
      description,
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

export default async function ArticleDetailPage({
  params,
}: {
  params: Promise<{ slug: string; locale: string }>;
}) {
  const { slug, locale } = await params;
  const [article, t] = await Promise.all([
    getArticleBySlug(slug, locale),
    getTranslations({ locale, namespace: "blog" }),
  ]);

  if (!article) {
    notFound();
  }

  const articleImage = article.media[0] ? getMediaUrl(article.media[0].filePath) : undefined;
  const articleJsonLd = {
    "@context": "https://schema.org",
    "@type": "Article",
    headline: article.title,
    description: getArticleExcerpt(article.content),
    image: articleImage ? [articleImage] : undefined,
    author: { "@type": "Person", name: siteConfig.name },
  };

  return (
    <article className="px-6 py-16">
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: jsonLdScript(articleJsonLd) }}
      />
      <div className="mx-auto max-w-[760px]">
        {article.tags.length > 0 && (
          <div className="mb-4 flex flex-wrap gap-1.5">
            {article.tags.map((tag) => (
              <Badge key={tag.id} variant="outline">
                {tag.name}
              </Badge>
            ))}
          </div>
        )}
        <h1 className="mb-8 text-[clamp(2.25rem,4.5vw,3.75rem)] leading-[1.14]">
          {article.title}
        </h1>
        {/* Contenu HTML rédigé côté admin Symfony (ROLE_ADMIN), sanitisé côté
            serveur en défense en profondeur avant injection. */}
        <div
          className="article-body opacity-85"
          dangerouslySetInnerHTML={{ __html: sanitizeArticleHtml(article.content) }}
        />
        <div className="mt-10 border-t border-[var(--border-softer)] pt-6">
          <ButtonLink href="/blog" variant="secondary">
            {t("eyebrow")} ←
          </ButtonLink>
        </div>
      </div>
    </article>
  );
}
