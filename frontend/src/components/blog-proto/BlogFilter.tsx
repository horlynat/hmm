"use client";

import { useMemo, useState, type ReactNode } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import type { Article } from "@/lib/types";
import { BlogCover } from "./BlogCover";
import { BLOG_THEMES, articleLabel, matchesTheme, type BlogTheme } from "./blogThemes";
import { ProtoIcon } from "@/components/proto/ProtoSvg";

/**
 * Page Blog du prototype (`pBlog()`) : en-tête `.ph` (fil d'Ariane, titre,
 * chapô et filtres `.bfil` par thème éditorial), puis le premier article mis
 * en avant (`.bfeat`) et les suivants en grille (`.bgrid`). Les filtres sont
 * dans l'en-tête comme dans le prototype : l'en-tête (rendu serveur) est donc
 * reçu en `head`, et la suite de la section (`.askq`) en `children`.
 */
export function BlogFilter({
  articles,
  excerpts,
  head,
  children,
}: {
  articles: Article[];
  excerpts: Map<string, string>;
  head: ReactNode;
  children?: ReactNode;
}) {
  const t = useTranslations("blogPage");
  const [theme, setTheme] = useState<BlogTheme | "tous">("tous");

  // Plus récent d'abord (id décroissant : le back-office n'expose pas de date).
  const sorted = useMemo(() => [...articles].sort((a, b) => b.id - a.id), [articles]);
  const filtered = sorted.filter((a) => matchesTheme(a, theme));
  const [featured, ...rest] = filtered;

  return (
    <>
      <section className="ph">
        <div className="w">
          {head}
          <div className="bfil" role="group" aria-label={t("filterLabel")}>
            <button type="button" aria-pressed={theme === "tous"} onClick={() => setTheme("tous")}>
              {t("filterAll")}
            </button>
            {BLOG_THEMES.map((th) => (
              <button key={th} type="button" aria-pressed={theme === th} onClick={() => setTheme(th)}>
                {t(`themes.${th}`)}
              </button>
            ))}
          </div>
        </div>
      </section>
      <section className="sec" style={{ paddingTop: "56px" }}>
        <div className="w">
          {featured && (
            <Link className="bfeat" href={{ pathname: "/blog/[slug]", params: { slug: featured.slug } }}>
              <BlogCover article={featured} large sizes="(max-width: 900px) 100vw, 640px" />
              <div className="bfb">
                {featured.tags.length > 0 && <span className="dom">{articleLabel(featured)}</span>}
                <h2>{featured.title}</h2>
                <p>{excerpts.get(featured.slug)}</p>
                <span className="lnk">
                  {t("readMore")} <ProtoIcon name="ar" />
                </span>
              </div>
            </Link>
          )}

          {rest.length > 0 && (
            <div className={rest.length === 1 ? "bgrid one" : "bgrid"}>
              {rest.map((a) => (
                <Link key={a.id} className="bcard" href={{ pathname: "/blog/[slug]", params: { slug: a.slug } }}>
                  <BlogCover article={a} sizes="(max-width: 900px) 100vw, 420px" />
                  <div>
                    {a.tags.length > 0 && <span className="dom">{articleLabel(a)}</span>}
                    <h3>{a.title}</h3>
                    <p>{excerpts.get(a.slug)}</p>
                  </div>
                </Link>
              ))}
            </div>
          )}

          {filtered.length === 0 && (
            <div className="empty" role="status">
              <h3>{articles.length === 0 ? t("empty") : t("noMatch")}</h3>
              {articles.length > 0 && (
                <button type="button" className="b b-g" style={{ marginTop: "16px" }} onClick={() => setTheme("tous")}>
                  {t("showAll")}
                </button>
              )}
            </div>
          )}

          {children}
        </div>
      </section>
    </>
  );
}
