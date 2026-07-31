import "server-only";
import sanitizeHtml from "sanitize-html";

/**
 * Sanitise le HTML des articles (rédigé côté admin Symfony) avant injection
 * via `dangerouslySetInnerHTML`. Défense en profondeur : même si la source est
 * de confiance, on neutralise tout script / gestionnaire d'événement inline
 * (`onerror`, `onclick`…) et tout schéma d'URL dangereux (`javascript:`), qui
 * s'exécuteraient sinon (la CSP du site autorise l'inline pour Next/Tailwind).
 *
 * La liste blanche couvre exactement les balises stylées par `.article-body`
 * dans globals.css, plus la mise en forme usuelle.
 */
export function sanitizeArticleHtml(dirty: string): string {
  return sanitizeHtml(dirty, {
    allowedTags: [
      "p", "br", "hr",
      "h2", "h3", "h4",
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
  });
}

/** Extrait un résumé texte brut du contenu d'un article, pour les meta description / JSON-LD. */
export function getArticleExcerpt(dirty: string, maxLength = 155): string {
  const text = sanitizeHtml(dirty, { allowedTags: [], allowedAttributes: {} })
    .replace(/\s+/g, " ")
    .trim();

  if (text.length <= maxLength) return text;
  return `${text.slice(0, maxLength - 1).trimEnd()}…`;
}
