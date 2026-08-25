/**
 * Entités HTML nommées produites par l'éditeur riche admin (espaces
 * insécables, apostrophe/guillemets numériques, tirets et guillemets
 * typographiques issus d'un copier-coller Word). Pas besoin d'une table
 * exhaustive : le contenu est rédigé en UTF-8, ces entités sont les seules
 * rencontrées en pratique.
 */
const NAMED_ENTITIES: Record<string, string> = {
  amp: "&",
  lt: "<",
  gt: ">",
  quot: '"',
  apos: "'",
  nbsp: " ",
  ndash: "–",
  mdash: "—",
  hellip: "…",
  ldquo: "“",
  rdquo: "”",
  lsquo: "‘",
  rsquo: "’",
};

function decodeHtmlEntities(text: string): string {
  return text.replace(/&(#\d+|#x[0-9a-f]+|[a-z]+);/gi, (match, entity: string) => {
    if (entity[0] === "#") {
      const code =
        entity[1].toLowerCase() === "x" ? parseInt(entity.slice(2), 16) : parseInt(entity.slice(1), 10);
      return Number.isNaN(code) ? match : String.fromCodePoint(code);
    }
    return NAMED_ENTITIES[entity.toLowerCase()] ?? match;
  });
}

/**
 * Extrait un résumé texte brut d'un contenu HTML (description de projet ou
 * corps d'article, rédigés via l'éditeur riche admin Symfony) pour affichage
 * sur une carte : retire les balises et décode les entités HTML restantes
 * (`&nbsp;`, `&#39;`…), sans quoi elles apparaîtraient telles quelles.
 *
 * Volontairement dépourvu de dépendance à sanitize-html ("server-only" côté
 * lib/sanitize.ts) pour rester utilisable dans les composants client — le
 * résultat n'est de toute façon jamais réinjecté en HTML, juste affiché comme
 * texte JSX (échappé automatiquement par React).
 */
export function getExcerpt(html: string, maxLength: number): string {
  const text = decodeHtmlEntities(html.replace(/<[^>]+>/g, " "))
    .replace(/\s+/g, " ")
    .trim();
  return text.length > maxLength ? `${text.slice(0, maxLength - 1).trimEnd()}…` : text;
}
