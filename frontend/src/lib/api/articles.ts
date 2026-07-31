import { apiFetch, extractCollection, pickLocalized } from "./client";
import type { Article } from "@/lib/types";

// Contenu publié plus souvent que projets/témoignages (cf. projects.ts) — on
// garde le défaut de client.ts (1h) plutôt que le filet de sécurité de 24h.
const ARTICLES_REVALIDATE_SECONDS = 60 * 60;

interface RawArticle extends Article {
  titleEn?: string | null;
  contentEn?: string | null;
}

/** `locale` explicite — cf. commentaire dans projects.ts (bug de mémoïsation de `getLocale()`). */
export async function getArticles(locale: string): Promise<Article[]> {
  const payload = await apiFetch<unknown>("/articles", {
    tags: ["articles"],
    revalidate: ARTICLES_REVALIDATE_SECONDS,
  });
  return extractCollection<RawArticle>(payload)
    .map(({ titleEn, contentEn, ...article }) => ({
      ...article,
      title: pickLocalized(article.title, titleEn, locale),
      content: pickLocalized(article.content, contentEn, locale),
    }))
    .sort((a, b) => b.id - a.id);
}

export async function getArticleBySlug(slug: string, locale: string): Promise<Article | null> {
  // Cf. projects.ts : filtre sur la collection, URI Template exacte non confirmée.
  const articles = await getArticles(locale);
  return articles.find((article) => article.slug === slug) ?? null;
}
