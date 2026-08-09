import { getTranslations } from "next-intl/server";
import { Badge, ButtonLink, HeroBackground, Reveal } from "@/components/ui";
import { ArticleCard } from "@/components/sections/ArticleCard";
import { NewsletterForm } from "@/components/sections/NewsletterForm";
import { getArticles } from "@/lib/api/articles";

export const dynamic = "force-static";

export default async function BlogPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "blog" });
  const tc = await getTranslations({ locale, namespace: "common" });
  const articles = await getArticles(locale);

  return (
    <>
      <section className="relative overflow-hidden px-6 pt-16 pb-20">
        <HeroBackground />
        <div className="relative mx-auto max-w-[1120px]">
          <Badge variant="accent" className="hero-in mb-4" style={{ animationDelay: "0s" }}>
            {t("eyebrow")}
          </Badge>
          {/* Pas d'animation ici : candidat LCP le plus probable de la page. */}
          <h1 className="mb-5 max-w-[22ch] text-[clamp(1.75rem,3vw,2.75rem)] leading-[1.25]">
            {t("title")} <span className="text-brand-primary">{t("titleAccent")}</span>
          </h1>
          <p
            className="hero-in mb-7 max-w-[60ch] text-[1.05rem] opacity-75"
            style={{ animationDelay: "0.16s" }}
          >
            {t("sub")}
          </p>
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
        </div>
      </section>

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
