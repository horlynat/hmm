import "server-only";
import type { SessionUser } from "@/lib/types";

/**
 * Le compte doit-il activer la 2FA avant d'accéder à l'espace membre ?
 * Même frontière que le backend (App\EventSubscriber\AccountStatusSubscriber
 * côté web, règle `2fa_required` sur /api/me/** côté API) : ROLE_EDITOR
 * (collaborateur vetté par un admin) en est exempté, tout le reste doit
 * l'activer. Source unique pour le layout /compte ET les routes qui ne
 * passent pas par lui (ex. /api/me/export, qui relaie GET /api/me — route
 * exemptée côté backend, donc à contrôler ici).
 */
export function mustSetUpTwoFactor(
  user: Pick<SessionUser, "roles" | "isTwoFactorEnabled" | "isTwoFactorRequired">,
): boolean {
  if (user.isTwoFactorEnabled === true) return false;
  // Contre-audit B2 : le backend expose sa propre règle (TwoFactorMandatePolicy,
  // hiérarchie des rôles comprise) — c'est elle qui fait foi. L'ancienne
  // approximation sur les rôles bruts exigeait la 2FA d'un modérateur/manager
  // (non obligatoire côté backend). Repli sur cette approximation, plus
  // stricte, seulement si le champ est absent ou invalide.
  if (typeof user.isTwoFactorRequired === "boolean") return user.isTwoFactorRequired;
  return !user.roles.includes("ROLE_EDITOR");
}
