<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Source unique de la règle « la 2FA est-elle obligatoire pour ce compte ? »
 * (P1 #13 / Info 18) — consultée par l'espace membre web
 * (AccountStatusSubscriber, TwoFactorController), le back-office web (mêmes
 * classes) et l'API (ApiTwoFactorEnforcementSubscriber). Centraliser évite
 * que les trois évoluent indépendamment et finissent par diverger.
 *
 * Obligatoire pour :
 * - tout compte sans ROLE_EDITOR (espace membre : clients/freelances) ;
 * - tout compte ROLE_ADMIN ou ROLE_SUPER_ADMIN (back-office), INDÉPENDAMMENT
 *   de l'élévation PAM courante — classification du compte, pas de la
 *   session (cf. User::getPrimaryRole(), basé sur le droit acquis brut,
 *   jamais filtré par isSuperAdminElevated() — un Super Admin dormant reste
 *   classé Super Admin ici, comme dans AccountLinkResolver::isBackOfficeUser()).
 *
 * PAS obligatoire pour ROLE_EDITOR/MODERATOR/MANAGER (back-office, rangs
 * intermédiaires) : hors périmètre de la demande initiale.
 */
final class TwoFactorMandatePolicy
{
    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
    ) {
    }

    public function isMandatoryFor(User $user): bool
    {
        $isEditorOrAbove = \in_array('ROLE_EDITOR', $this->roleHierarchy->getReachableRoleNames($user->getRoles()), true);
        $isAdminOrAbove = \in_array($user->getPrimaryRole(), ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], true);

        return !$isEditorOrAbove || $isAdminOrAbove;
    }

    public function needsEnforcement(User $user): bool
    {
        return $this->isMandatoryFor($user) && !$user->isTotpAuthenticationEnabled();
    }
}
