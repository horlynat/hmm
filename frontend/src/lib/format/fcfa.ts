/**
 * Prix en francs CFA, sans décimales, séparateur de milliers de la langue
 * (« 199 999 FCFA » en français, « 199,999 FCFA » en anglais). L'unité est
 * accolée par une espace insécable pour ne jamais se retrouver seule en début
 * de ligne.
 */
export function formatFcfa(amount: number, locale: string): string {
  const formatted = new Intl.NumberFormat(locale === "en" ? "en-US" : "fr-FR", {
    maximumFractionDigits: 0,
  }).format(amount);
  return `${formatted} FCFA`;
}
