import { apiFetch, extractCollection } from "./client";
import type { Article } from "@/lib/types";

export async function getArticles(): Promise<Article[]> {
  const payload = await apiFetch<unknown>("/articles", { tags: ["articles"] });
  return extractCollection<Article>(payload).sort((a, b) => b.id - a.id);
}

export async function getArticleBySlug(slug: string): Promise<Article | null> {
  // Cf. projects.ts : filtre sur la collection, URI Template exacte non confirmée.
  const articles = await getArticles();
  return articles.find((article) => article.slug === slug) ?? null;
}
