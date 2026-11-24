/**
 * Prix en francs CFA, sans décimales, séparateur de milliers de la langue
 * (« 199 999 FCFA » en français, « 199,999 FCFA » en anglais). Comme le
 * prototype de refonte, tous les blancs sont des espaces fines insécables
 * (U+202F) : l'unité ne se retrouve jamais seule en début de ligne.
 */
export function formatFcfa(amount: number, locale: string): string {
  const formatted = new Intl.NumberFormat(locale === "en" ? "en-US" : "fr-FR", {
    maximumFractionDigits: 0,
  })
    .format(amount)
    .replace(/[\u202f\u00a0 ]/g, "\u202f");
  return `${formatted}\u202fFCFA`;
}
