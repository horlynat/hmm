"use client";

import { useMemo, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import type { Article } from "@/lib/types";
import { BlogCover } from "./BlogCover";
import { ProtoIcon } from "@/components/proto/ProtoSvg";

/**
 * Filtrage par thème du prototype (`.bfil`), sur les tags réels des
 * articles publiés — pas une liste de thèmes figée. Le premier article
 * (le plus récent, `id` décroissant) est mis en avant (`.bfeat`), les
 * suivants en grille (`.bgrid`).
 */
export function BlogFilter({ articles, excerpts }: { articles: Article[]; excerpts: Map<string, string> }) {
  const t = useTranslations("blogPage");
  const [tag, setTag] = useState<number | "tous">("tous");

  const topics = useMemo(() => {
    const seen = new Map<number, string>();
    for (const a of articles) for (const tg of a.tags) seen.set(tg.id, tg.name);
    return [...seen.entries()].sort((a, b) => a[1].localeCompare(b[1]));
  }, [articles]);

  const sorted = useMemo(() => [...articles].sort((a, b) => b.id - a.id), [articles]);
  const filtered = tag === "tous" ? sorted : sorted.filter((a) => a.tags.some((tg) => tg.id === tag));
  const [featured, ...rest] = filtered;

  return (
    <>
      {topics.length > 0 && (
        <div className="bfil" role="group" aria-label={t("filterLabel")}>
          <button type="button" aria-pressed={tag === "tous"} onClick={() => setTag("tous")}>
            {t("filterAll")}
          </button>
          {topics.map(([id, name]) => (
            <button key={id} type="button" aria-pressed={tag === id} onClick={() => setTag(id)}>
              {name}
            </button>
          ))}
        </div>
      )}

      {featured && (
        <Link className="bfeat" href={{ pathname: "/blog/[slug]", params: { slug: featured.slug } }}>
          <BlogCover article={featured} large sizes="(max-width: 900px) 100vw, 640px" />
          <div className="bfb">
            {featured.tags.length > 0 && <span className="dom">{featured.tags.map((tg) => tg.name).join(" · ")}</span>}
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
                {a.tags.length > 0 && <span className="dom">{a.tags.map((tg) => tg.name).join(" · ")}</span>}
                <h3>{a.title}</h3>
                <p>{excerpts.get(a.slug)}</p>
              </div>
            </Link>
          ))}
        </div>
      )}

      {filtered.length === 0 && (
        <p className="mut" role="status">
          {t("noMatch")}
        </p>
      )}
    </>
  );
}
