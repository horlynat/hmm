import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { getArticles } from "@/lib/api/articles";
import { getArticleExcerpt } from "@/lib/sanitize";
import { BlogFilter } from "./BlogFilter";
import { AskAssistantRow } from "./AskAssistantRow";

/**
 * Page Blog — markup du prototype (`pBlog()` : `.ph .crumb .bfil .bfeat
 * .bgrid .askq`), sans bandeau d'appel final (le prototype n'en a pas ici).
 * Aucun article « à paraître » fictif n'est annoncé : seuls les articles
 * réellement publiés sont listés.
 */
export async function SiteBlog({ locale }: { locale: string }) {
  const [t, tc, articles] = await Promise.all([
    getTranslations({ locale, namespace: "blogPage" }),
    getTranslations({ locale, namespace: "catalog" }),
    getArticles(locale),
  ]);
  // Chapô du back-office ; à défaut, extrait du contenu.
  const excerpts = new Map(articles.map((a) => [a.slug, a.summary ?? getArticleExcerpt(a.content, 180)]));

  return (
    <BlogFilter
      articles={articles}
      excerpts={excerpts}
      head={
        <>
          <p className="crumb">
            <Link href="/">{tc("crumbHome")}</Link> / {t("crumb")}
          </p>
          <h1>
            {t("title")}
            <span className="grad">{t("titleAccent")}</span>
          </h1>
          <p>{t("lede")}</p>
        </>
      }
    >
      <div className="askq">
        <div>
          <b>{t("askTitle")}</b>
          <span>{t("askText")}</span>
        </div>
        <AskAssistantRow />
      </div>
    </BlogFilter>
  );
}
