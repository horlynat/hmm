/**
 * Normalise un libellé de catégorie pour comparaison tolérante (accents,
 * casse, espaces multiples) — utilisé pour faire correspondre les
 * catégories "phares" de la home (texte fixe, cf. `featuredCategories.ts`)
 * aux catégories réelles reçues de l'API, dont le libellé est localisé
 * (fr/en) et saisi librement par l'admin.
 */
export function normalizeCategoryName(name: string): string {
  return name
    .normalize("NFD")
    .replace(/\p{Diacritic}/gu, "")
    .toLowerCase()
    .trim()
    .replace(/\s+/g, " ");
}
