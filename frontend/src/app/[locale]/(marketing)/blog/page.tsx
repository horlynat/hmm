import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { Newspaper, Tag as TagIcon } from "lucide-react";
import { Badge, ButtonLink, Reveal, StatCard } from "@/components/ui";
import { PageHero } from "@/components/sections/PageHero";
import { ArticleCard } from "@/components/sections/ArticleCard";
import { NewsletterForm } from "@/components/sections/NewsletterForm";
import { getArticles } from "@/lib/api/articles";
import { buildPageMetadata } from "@/lib/metadata";

export const dynamic = "force-static";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string }>;
}): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "blog" });
  return buildPageMetadata({
    locale,
    pathname: "/blog",
    title: `${t("title")} ${t("titleAccent")}`,
    description: t("sub"),
  });
}

export default async function BlogPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "blog" });
  const tc = await getTranslations({ locale, namespace: "common" });
  const articles = await getArticles(locale);
  // Nombre de sujets distincts réellement couverts par les articles publiés
  // (pas de contenu inventé : dérivé des tags déjà chargés depuis l'API).
  const topicsCount = new Set(articles.flatMap((article) => article.tags.map((tag) => tag.id))).size;

  return (
    <>
      <PageHero
        eyebrow={t("eyebrow")}
        title={t("title")}
        titleAccent={t("titleAccent")}
        titleClassName="max-w-[22ch]"
        subtitle={t("sub")}
        subtitleClassName="max-w-[60ch]"
        aside={
          <div className="hero-in flex flex-wrap gap-3" style={{ animationDelay: "0.16s" }}>
            <StatCard icon={Newspaper} label={t("stats.articles")} value={articles.length} />
            {topicsCount > 0 && (
              <StatCard icon={TagIcon} label={t("stats.topics")} value={topicsCount} />
            )}
          </div>
        }
      >
        <div
          className="hero-in mb-7 flex flex-wrap items-center gap-x-6 gap-y-3"
          style={{ animationDelay: "0.24s" }}
        >
          <ButtonLink href="/contact">{tc("ctaConfierProjet")}</ButtonLink>
          <a href="#newsletter" className="text-sm font-semibold text-brand-primary hover:underline">
            {t("newsletter.submit")} →
          </a>
        </div>
        <div className="hero-in flex flex-wrap gap-2.5" style={{ animationDelay: "0.32s" }}>
          <Badge variant="accent">Symfony</Badge>
          <Badge variant="accent">API Platform</Badge>
          <Badge variant="accent">Next.js</Badge>
          <Badge variant="outline">Assistant IA</Badge>
          <Badge variant="outline">Cybersécurité</Badge>
        </div>
      </PageHero>

      <section id="articles" className="border-y border-[var(--border-softer)] bg-bg-card px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          {articles.length > 0 ? (
            <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
              {articles.map((article) => (
                <ArticleCard key={article.id} article={article} />
              ))}
            </div>
          ) : (
            <Reveal delay={0} className="card border-dashed py-10 text-center">
              <p className="mx-auto max-w-[52ch] text-sm opacity-70">{t("empty")}</p>
            </Reveal>
          )}
        </div>
      </section>

      <NewsletterForm />
    </>
  );
}
