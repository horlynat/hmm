<?php

namespace App\Security\Voter;

/**
 * Permissions sur le tableau de bord (aucun sujet : actions globales).
 *
 * - VIEW       : Éditeur et plus (accès de base au back-office).
 * - VIEW_STATS : Modérateur et plus.
 * - EXPORT     : Manager et plus (extraction de données).
 * - VIEW_LOGS  : Administrateur et plus.
 */
class DashboardVoter extends AbstractRoleVoter
{
    public const VIEW = 'DASHBOARD_VIEW';
    public const VIEW_STATS = 'DASHBOARD_VIEW_STATS';
    public const EXPORT = 'DASHBOARD_EXPORT';
    public const VIEW_LOGS = 'DASHBOARD_VIEW_LOGS';

    protected function getRequiredRole(string $attribute, mixed $subject): ?string
    {
        if (null !== $subject) {
            return null;
        }

        return match ($attribute) {
            self::VIEW => 'ROLE_ADMIN',
            self::VIEW_STATS => 'ROLE_ADMIN',
            self::EXPORT => 'ROLE_ADMIN',
            self::VIEW_LOGS => 'ROLE_ADMIN',
            default => null,
        };
    }
}
