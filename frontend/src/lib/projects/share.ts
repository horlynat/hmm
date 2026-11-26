import { firstParagraph, leadSentences } from "@/lib/projects/caseText";
import { getMediaUrl } from "@/lib/media";
import { SITE_URL } from "@/lib/metadata";

/**
 * Image de partage : les réseaux (Facebook, LinkedIn, X) n'affichent pas les
 * SVG — une couverture SVG retombe donc sur l'image générée de la locale.
 */
export function shareImage(coverPath: string | null | undefined, locale: string): string {
  return coverPath && !/\.svgz?(?:$|[?#])/i.test(coverPath) ? getMediaUrl(coverPath) : `${SITE_URL}/${locale}/opengraph-image`;
}

/** Résumé court (≤ 160 caractères, coupé à une fin de phrase) : le résultat, sinon le début de la description. */
export function shareDescription(result: string | null | undefined, descriptionHtml: string): string {
  return leadSentences(result?.trim() || firstParagraph(descriptionHtml), 160);
}

/** Titre de la fiche : sans « — Horlynat » quand le titre du projet porte déjà le nom. */
export function pageTitle(title: string): string | { absolute: string } {
  return /\bhorlynat\b/i.test(title) ? { absolute: title } : title;
}
