<?php

namespace App\Security\Voter;

use App\Entity\User;

/**
 * Factorise la résolution d'un rôle en tenant compte de la hiérarchie
 * (ex: un ROLE_MANAGER satisfait une exigence ROLE_EDITOR) pour tous les
 * Voters de l'application, qu'ils étendent AbstractRoleVoter ou non.
 *
 * La classe utilisatrice doit déclarer elle-même sa propre propriété
 * `RoleHierarchyInterface $roleHierarchy` (via son constructeur) : ce trait
 * ne fait que consommer `$this->roleHierarchy`, il ne la déclare pas, pour
 * éviter tout conflit avec la promotion de propriété du constructeur.
 */
trait RoleHierarchyAwareTrait
{
    protected function hasRole(User $user, string $role): bool
    {
        return in_array($role, $this->roleHierarchy->getReachableRoleNames($user->getRoles()), true);
    }
}
