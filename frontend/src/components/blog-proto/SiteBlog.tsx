import { getTranslations } from "next-intl/server";
import { getArticles } from "@/lib/api/articles";
import { getArticleExcerpt } from "@/lib/sanitize";
import { BlogFilter } from "./BlogFilter";
import { AskAssistantRow } from "./AskAssistantRow";
import { ProtoCta } from "@/components/proto/ProtoSections";

/**
 * Page Blog — markup du prototype (`.ph .bfil .bfeat .bgrid`). Thèmes de
 * filtrage dérivés des tags réellement portés par les articles publiés (pas
 * de liste figée) ; aucun article « à paraître » fictif (contrairement au
 * prototype) — rien n'est annoncé avant d'être réellement publié.
 */
export async function SiteBlog({ locale }: { locale: string }) {
  const [t, articles] = await Promise.all([
    getTranslations({ locale, namespace: "blogPage" }),
    getArticles(locale),
  ]);
  const excerpts = new Map(articles.map((a) => [a.slug, getArticleExcerpt(a.content, 180)]));

  return (
    <>
      <section className="ph">
        <div className="w">
          <h1>
            {t("title")}
            <span className="grad">{t("titleAccent")}</span>
          </h1>
          <p>{t("lede")}</p>
        </div>
      </section>
      <section className="sec" style={{ paddingTop: "56px" }}>
        <div className="w">
          {articles.length > 0 ? (
            <BlogFilter articles={articles} excerpts={excerpts} />
          ) : (
            <p className="mut">{t("empty")}</p>
          )}
          <div className="askq">
            <div>
              <b>{t("askTitle")}</b>
              <span>{t("askText")}</span>
            </div>
            <AskAssistantRow />
          </div>
        </div>
      </section>
      <ProtoCta locale={locale} />
    </>
  );
}
