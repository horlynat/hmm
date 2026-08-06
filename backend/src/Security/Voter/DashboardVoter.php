<?php

namespace App\Security\Voter;

/**
 * Permissions sur le tableau de bord (aucun sujet : actions globales).
 *
 * Toutes réservées à l'Administrateur et plus (fail-closed) — VIEW/VIEW_STATS
 * ne sont pas ouvertes à un rang inférieur malgré ce qu'un docblock antérieur
 * laissait entendre, aucune route ne l'exigeait moins strict.
 */
class DashboardVoter extends AbstractRoleVoter
{
    public const VIEW = 'DASHBOARD_VIEW';
    public const VIEW_STATS = 'DASHBOARD_VIEW_STATS';
    public const VIEW_LOGS = 'DASHBOARD_VIEW_LOGS';

    protected function getRequiredRole(string $attribute, mixed $subject): ?string
    {
        if (null !== $subject) {
            return null;
        }

        return match ($attribute) {
            self::VIEW => 'ROLE_ADMIN',
            self::VIEW_STATS => 'ROLE_ADMIN',
            self::VIEW_LOGS => 'ROLE_ADMIN',
            default => null,
        };
    }
}
