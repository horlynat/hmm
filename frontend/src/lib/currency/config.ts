/**
 * Devise d'affichage sélectionnée par le visiteur — cookie lisible côté
 * serveur (pas localStorage, cf. src/lib/currency/actions.ts) car les
 * montants convertis sont rendus côté serveur (SSR) sur /compte/*.
 */

/** Nom du cookie de préférence de devise. */
export const CURRENCY_COOKIE = "hmm_currency";

/** Doit rester synchronisé avec App\Enum\CurrencyEnum côté backend. */
export const CURRENCIES = ["USD", "EUR", "XAF"] as const;
export type Currency = (typeof CURRENCIES)[number];

export const DEFAULT_CURRENCY: Currency = "USD";

export function isCurrency(value: string | undefined | null): value is Currency {
  return null != value && (CURRENCIES as readonly string[]).includes(value);
}
