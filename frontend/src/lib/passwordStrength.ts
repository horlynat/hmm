/**
 * 5 critères cumulés (longueur, majuscule, minuscule, chiffre, caractère
 * spécial) — aligné sur `STRONG_PASSWORD_RE` (validation serveur). Partagé
 * entre `PasswordStrength` (espace compte) et le compteur de robustesse du
 * prototype (`.meter`, mêmes 5 paliers).
 */
export function scorePassword(value: string): number {
  if (!value) return 0;
  const checks = [value.length >= 8, /[A-Z]/.test(value), /[a-z]/.test(value), /\d/.test(value), /[\W_]/.test(value)];
  return checks.filter(Boolean).length;
}
