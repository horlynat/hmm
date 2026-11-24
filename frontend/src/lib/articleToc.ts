/**
 * Découpe le HTML assaini d'un article (sanitizeArticleHtml, balises en
 * minuscules) en sections par titre de niveau 2, pour le sommaire de la
 * fiche article (`.atoc`) et les ancres de défilement (`.ase#<id>`).
 */
export interface ArticleSection {
  id: string;
  heading: string;
  bodyHtml: string;
}

export interface ArticleOutline {
  introHtml: string | null;
  sections: ArticleSection[];
}

function slugify(text: string): string {
  return text
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/(^-+|-+$)/g, "");
}

export function splitArticleSections(html: string): ArticleOutline {
  const parts = html.split(/(<h2[^>]*>[\s\S]*?<\/h2>)/gi);
  let introHtml: string | null = null;
  let i = 0;
  if (parts.length > 0 && !/^\s*<h2/i.test(parts[0])) {
    introHtml = parts[0].trim() || null;
    i = 1;
  }

  const used = new Set<string>();
  const sections: ArticleSection[] = [];
  for (; i < parts.length; i += 2) {
    const headingTag = parts[i];
    if (!headingTag) continue;
    const heading = headingTag.replace(/<[^>]+>/g, "").trim();
    if (!heading) continue;

    const base = slugify(heading) || "section";
    let id = base;
    let n = 2;
    while (used.has(id)) id = `${base}-${n++}`;
    used.add(id);

    sections.push({ id, heading, bodyHtml: (parts[i + 1] ?? "").trim() });
  }

  return { introHtml, sections };
}
