import type { Article } from "@/lib/types";

/**
 * Thèmes éditoriaux du blog, repris du prototype (`pBlog()` : « Tous les
 * thèmes », Architecture, Cybersécurité, Gestion des risques, IA) et
 * rapprochés des tags réels des articles par mots-clés, en français comme en
 * anglais. La règle du prototype pour « IA » (`/Ingénierie|Présentation/`)
 * est conservée.
 */
export const BLOG_THEMES = ["architecture", "cyber", "risques", "ia"] as const;
export type BlogTheme = (typeof BLOG_THEMES)[number];

const THEME_TESTS: Record<BlogTheme, RegExp> = {
  architecture: /architect/,
  cyber: /cyber/,
  risques: /risque|risk/,
  ia: /\bia\b|\bai\b|intelligence|ingenierie|engineering|presentation|introduction/,
};

/** Libellé de catégorie de l'article (`.dom` du prototype) : ses tags joints. */
export function articleLabel(article: Article): string {
  return article.tags.map((t) => t.name).join(" · ");
}

function normalized(article: Article): string {
  return articleLabel(article)
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .toLowerCase();
}

export function matchesTheme(article: Article, theme: BlogTheme | "tous"): boolean {
  return theme === "tous" || THEME_TESTS[theme].test(normalized(article));
}

/** Icône et teinte de vignette par catégorie (`BCAT` du prototype). */
export const BCAT: Record<string, ["arch" | "spark" | "umb" | "lock" | "shop", number]> = {
  architecture: ["arch", 212],
  presentation: ["spark", 30],
  risques: ["umb", 42],
  cyber: ["lock", 200],
  ingenierie: ["shop", 150],
};

/** Icône et teinte de l'article (repli du prototype : étincelle, 212). */
export function articleVisual(article: Article): ["arch" | "spark" | "umb" | "lock" | "shop", number] {
  return BCAT[articleCategory(article)] ?? ["spark", 212];
}

/** Catégorie visuelle de la vignette (clé de `BCAT` dans BlogCover). */
export function articleCategory(article: Article): string {
  const s = normalized(article);
  if (/presentation|introduction/.test(s)) return "presentation";
  if (/cyber/.test(s)) return "cyber";
  if (/risque|risk/.test(s)) return "risques";
  if (/architect|automat/.test(s)) return "architecture";
  if (/ingenierie|engineering/.test(s)) return "ingenierie";
  return "";
}
