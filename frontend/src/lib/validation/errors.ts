import type { Translator } from "./schemas";

/**
 * Traduit un code d'erreur renvoyé par une Server Action en message lisible.
 * - `rate_limited` → message dédié (namespace `validation`)
 * - `invalid_input` / `network_error` → message générique du formulaire
 * - sinon : détail API (ex: "email: déjà utilisé"), débarrassé du préfixe champ.
 *
 * @param tv traducteur du namespace `validation`
 * @param tf traducteur du formulaire courant (doit exposer une clé `error`)
 */
export function mapActionError(error: string, tv: Translator, tf: Translator): string {
  if (error === "rate_limited") return tv("rateLimited");
  if (error === "invalid_input" || error === "network_error") return tf("error");
  return error.replace(/^[a-zA-Z]+:\s*/, "");
}
