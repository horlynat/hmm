import type { Metadata } from "next";
import Image from "next/image";
import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { Badge, Breadcrumb, ButtonLink, Card, HeroBackground, ReadingProgressBar, Reveal } from "@/components/ui";
import { NextUpCard } from "@/components/sections/NextUpCard";
import { getArticleBySlug, getArticles } from "@/lib/api/articles";
import { sanitizeArticleHtml, getArticleExcerpt, getReadingTimeMinutes } from "@/lib/sanitize";
import { getMediaUrl } from "@/lib/media";
import { articleImageTransitionName } from "@/lib/viewTransitionNames";
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
  const [article, articles, t, tc] = await Promise.all([
    getArticleBySlug(slug, locale),
    getArticles(locale),
    getTranslations({ locale, namespace: "blog" }),
    getTranslations({ locale, namespace: "common" }),
  ]);

  if (!article) {
    notFound();
  }

  // Article suivant dans la liste (bouclé : le dernier renvoie au premier),
  // affiché en fin de page plutôt qu'un simple lien retour — cf. NextUpCard.
  // Masqué s'il n'y a qu'un seul article publié (n'aurait rien à proposer).
  const currentIndex = articles.findIndex((a) => a.slug === article.slug);
  const nextArticle = articles.length > 1 ? articles[(currentIndex + 1) % articles.length] : null;
  const nextArticleImage = nextArticle?.media[0] ? getMediaUrl(nextArticle.media[0].filePath) : undefined;

  const articleImage = article.media[0] ? getMediaUrl(article.media[0].filePath) : undefined;
  const readingTime = getReadingTimeMinutes(article.content);
  const articleJsonLd = {
    "@context": "https://schema.org",
    "@type": "Article",
    headline: article.title,
    description: getArticleExcerpt(article.content),
    image: articleImage ? [articleImage] : undefined,
    author: { "@type": "Person", name: siteConfig.name },
  };

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: jsonLdScript(articleJsonLd) }}
      />
      <ReadingProgressBar />

      {/* Même habillage (fond dégradé + grille de points) que le hero de
          toutes les autres pages publiques, cf. PageHero — jusqu'ici absent
          des pages de détail, qui tranchaient à plat sur le reste du site. */}
      <section className="relative overflow-hidden px-6 pt-14 pb-8">
        <HeroBackground />
        <div className="relative mx-auto max-w-[760px]">
          <div className="mb-6">
            <Breadcrumb items={[{ label: t("eyebrow"), href: "/blog" }, { label: article.title }]} />
          </div>
          <div className="hero-in mb-4 flex flex-wrap items-center gap-3" style={{ animationDelay: "0s" }}>
            <Badge variant="accent">{t("eyebrow")}</Badge>
            <span className="text-sm font-medium opacity-60">{t("readingTime", { minutes: readingTime })}</span>
          </div>
          {/* Volontairement pas de classe hero-in sur le h1 : candidat LCP le
              plus probable de la page, cf. commentaire dans PageHero.tsx. */}
          <h1 className="mb-4 text-[clamp(2.25rem,4.5vw,3.75rem)] leading-[1.14]">
            {article.title}
          </h1>
          {article.tags.length > 0 && (
            <div className="hero-in flex flex-wrap gap-1.5" style={{ animationDelay: "0.16s" }}>
              {article.tags.map((tag) => (
                <Badge key={tag.id} variant="outline">
                  {tag.name}
                </Badge>
              ))}
            </div>
          )}
        </div>
      </section>

      {articleImage && (
        <section className="px-6 pb-6">
          <div className="mx-auto max-w-[760px]">
            <Reveal delay={0}>
              <Card variant="soft" className="overflow-hidden p-0">
                <div
                  className="vt-target relative h-[240px] w-full bg-brand-light sm:h-[380px]"
                  style={{ viewTransitionName: articleImageTransitionName(article.id) }}
                >
                  <Image
                    src={articleImage}
                    alt={article.media[0]?.altText ?? article.title}
                    fill
                    sizes="760px"
                    className="object-cover"
                    priority
                  />
                </div>
              </Card>
            </Reveal>
          </div>
        </section>
      )}

      <section className="article-detail px-6 pt-2 pb-16">
        <div className="mx-auto max-w-[760px]">
          {/* Contenu HTML rédigé côté admin Symfony (ROLE_ADMIN), sanitisé côté
              serveur en défense en profondeur avant injection. */}
          <div
            className="article-body opacity-85"
            dangerouslySetInnerHTML={{ __html: sanitizeArticleHtml(article.content) }}
          />
          <div className="mt-10 border-t border-[var(--border-softer)] pt-6">
            <ButtonLink href="/blog" variant="secondary" className="mb-6">
              {t("eyebrow")} ←
            </ButtonLink>
            {nextArticle && (
              <Reveal delay={0}>
                <NextUpCard
                  eyebrow={t("nextArticle")}
                  title={nextArticle.title}
                  cta={tc("readMore")}
                  href={{ pathname: "/blog/[slug]", params: { slug: nextArticle.slug } }}
                  image={nextArticleImage}
                  imageAlt={nextArticle.media[0]?.altText ?? nextArticle.title}
                  imageTransitionName={articleImageTransitionName(nextArticle.id)}
                />
              </Reveal>
            )}
          </div>
        </div>
      </section>
    </>
  );
}
