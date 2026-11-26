import { getExcerpt } from "@/lib/text";

/**
 * Début d'un texte, coupé à la dernière fin de phrase avant `max` (jamais au
 * milieu d'un mot, ni de « … » quand une phrase entière tient).
 */
export function leadSentences(text: string, max: number): string {
  const clean = text.replace(/\s+/g, " ").trim();
  if (clean.length <= max) return clean;
  const head = clean.slice(0, max);
  const end = Math.max(head.lastIndexOf(". "), head.lastIndexOf("! "), head.lastIndexOf("? "));
  if (end >= max / 3) return head.slice(0, end + 1);
  const space = head.lastIndexOf(" ");
  return `${head.slice(0, space > 0 ? space : max - 1).replace(/[\s,;:—–-]+$/u, "")}…`;
}

/** Premier paragraphe d'une description HTML, en texte brut (le titre de section « Le projet » est ignoré). */
export function firstParagraph(html: string): string {
  const p = /<p[^>]*>([\s\S]*?)<\/p>/i.exec(html);
  return getExcerpt(p ? p[1] : html, 100000);
}

/** « Titre : texte » (typographie FR ou EN) → titre en gras + texte ; sinon tout en texte. */
export function splitLabel(label: string): { title: string | null; text: string } {
  const m = /^([^\n]{2,60}?)\s?:\s+([\s\S]+)$/u.exec(label.trim());
  return m ? { title: m[1].trim(), text: m[2].trim() } : { title: null, text: label.trim() };
}

/** Retire un préfixe qui répète le libellé (« Mon rôle — Architecte » sous « Mon rôle »). */
export function withoutLabelPrefix(value: string, label: string): string {
  const v = value.trim();
  if (!v.toLocaleLowerCase().startsWith(label.toLocaleLowerCase())) return v;
  const rest = v.slice(label.length).replace(/^[\s:—–-]+/u, "");
  return rest || v;
}
