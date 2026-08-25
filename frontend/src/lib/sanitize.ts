import "server-only";
import sanitizeHtml from "sanitize-html";

/**
 * Sanitise le HTML des contenus rédigés côté admin Symfony (éditeur riche —
 * cf. assets/controllers/rich_text_controller.js) avant injection via
 * `dangerouslySetInnerHTML` : corps d'article (Article.content) ET
 * description complète d'un projet (Project.description), même traitement
 * pour les deux — malgré le nom, pas spécifique aux articles. Défense en
 * profondeur : même si la source est de confiance, on neutralise tout script
 * / gestionnaire d'événement inline (`onerror`, `onclick`…) et tout schéma
 * d'URL dangereux (`javascript:`), qui s'exécuteraient sinon (la CSP du site
 * autorise l'inline pour Next/Tailwind).
 *
 * La liste blanche couvre exactement les balises stylées par `.article-body`
 * dans globals.css, plus la mise en forme usuelle.
 */
export function sanitizeArticleHtml(dirty: string): string {
  return normalizeNonBreakingSpaces(sanitizeHtml(dirty, {
    allowedTags: [
      "p", "br", "hr",
      "h1", "h2", "h3", "h4",
      "ul", "ol", "li",
      "a", "strong", "b", "em", "i", "u", "s",
      "blockquote", "code", "pre",
      "img", "figure", "figcaption",
      "table", "thead", "tbody", "tr", "th", "td",
      "span",
    ],
    allowedAttributes: {
      a: ["href", "title"],
      img: ["src", "alt", "title", "width", "height", "loading"],
      "*": ["class"],
    },
    // Schémas d'URL autorisés (bloque javascript:, data: sur les liens, etc.).
    allowedSchemes: ["http", "https", "mailto", "tel"],
    allowedSchemesByTag: { img: ["http", "https", "data"] },
    // Force des liens externes sûrs (pas de fuite d'opener, pas d'onglet détourné).
    transformTags: {
      a: (tagName, attribs) => ({
        tagName,
        attribs: {
          ...attribs,
          ...(attribs.href && /^https?:\/\//i.test(attribs.href)
            ? { target: "_blank", rel: "noopener noreferrer nofollow" }
            : {}),
        },
      }),
    },
    disallowedTagsMode: "discard",
  }));
}

/**
 * Remplace les espaces insécables (entité `&nbsp;` ou caractère U+00A0 déjà
 * décodé) par de vraies espaces normales. Sans ça, un contenu collé depuis
 * une source qui en truffe le texte (Word, certains exports) devient un seul
 * bloc insécable : le navigateur ne peut plus y couper de ligne, le texte
 * déborde de son conteneur au lieu de passer à la ligne (constaté en
 * pratique sur un article réel — cf. rich_text_controller.js pour le même
 * traitement côté saisie, qui empêche la pollution à la source).
 */
function normalizeNonBreakingSpaces(html: string): string {
  return html.replace(/&nbsp;/gi, " ").replace(/\u00a0/g, " ");
}

/** Extrait un résumé texte brut du contenu d'un article, pour les meta description / JSON-LD. */
export function getArticleExcerpt(dirty: string, maxLength = 155): string {
  const text = sanitizeHtml(dirty, { allowedTags: [], allowedAttributes: {} })
    .replace(/\s+/g, " ")
    .trim();

  if (text.length <= maxLength) return text;
  return `${text.slice(0, maxLength - 1).trimEnd()}…`;
}

/** Temps de lecture estimé (minutes, arrondi au supérieur, jamais 0) — ~200 mots/min en lecture silencieuse. */
export function getReadingTimeMinutes(dirty: string, wordsPerMinute = 200): number {
  const text = sanitizeHtml(dirty, { allowedTags: [], allowedAttributes: {} }).trim();
  const wordCount = text.length > 0 ? text.split(/\s+/).length : 0;
  return Math.max(1, Math.ceil(wordCount / wordsPerMinute));
}
