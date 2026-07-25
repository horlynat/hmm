import { getTranslations } from "next-intl/server";
import { Badge } from "@/components/ui";
import { ArticleCard, NewsletterForm } from "@/components/sections";
import { getArticles } from "@/lib/api/articles";

export default async function BlogPage() {
  const t = await getTranslations("blog");
  const articles = await getArticles();

  return (
    <>
      <section className="px-6 pt-14 pb-8">
        <div className="mx-auto max-w-[1120px]">
          <Badge variant="accent" className="mb-4">
            {t("eyebrow")}
          </Badge>
          <h1 className="mb-5 max-w-[22ch] text-[clamp(2rem,3.8vw,2.9rem)] leading-[1.14]">
            {t("title")} <span className="text-brand-primary">{t("titleAccent")}</span>
          </h1>
          <p className="max-w-[60ch] text-[1.05rem] opacity-75">{t("sub")}</p>
        </div>
      </section>

      <section className="px-6 py-10">
        <div className="mx-auto max-w-[1120px]">
          {articles.length > 0 ? (
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {articles.map((article) => (
                <ArticleCard key={article.id} article={article} />
              ))}
            </div>
          ) : (
            <p className="text-sm opacity-60">{t("empty")}</p>
          )}
        </div>
      </section>

      <section className="px-6 py-16">
        <NewsletterForm />
      </section>
    </>
  );
}
