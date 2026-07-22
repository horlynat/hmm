<?php

namespace App\Security\Voter;

use App\Entity\User;

/**
 * Permissions sur la section Sécurité & Audit du back-office.
 *
 * Toutes réservées à l'Administrateur et plus : c'est une zone sensible
 * (logs de connexion, 2FA, IPs, sessions), pas un outil de modération courant.
 *
 * - FORCE_LOGOUT prend un User en sujet (le compte à déconnecter de force) ;
 *   les autres actions sont globales (pas de sujet).
 */
class SecurityVoter extends AbstractRoleVoter
{
    public const VIEW_LOGS = 'SECURITY_VIEW_LOGS';
    public const MANAGE_2FA = 'SECURITY_MANAGE_2FA';
    public const FORCE_LOGOUT = 'SECURITY_FORCE_LOGOUT';
    public const VIEW_IPS = 'SECURITY_VIEW_IPS';
    public const MANAGE_SESSIONS = 'SECURITY_MANAGE_SESSIONS';
    public const VIEW_ROLES = 'SECURITY_VIEW_ROLES';
    public const VIEW_POLICIES = 'SECURITY_VIEW_POLICIES';
    public const VIEW_AUDIT = 'SECURITY_VIEW_AUDIT';

    protected function getRequiredRole(string $attribute, mixed $subject): ?string
    {
        return match (true) {
            self::FORCE_LOGOUT === $attribute && $subject instanceof User => 'ROLE_ADMIN',
            self::VIEW_LOGS === $attribute && null === $subject => 'ROLE_ADMIN',
            self::MANAGE_2FA === $attribute && (null === $subject || $subject instanceof User) => 'ROLE_ADMIN',
            self::VIEW_IPS === $attribute && null === $subject => 'ROLE_ADMIN',
            self::MANAGE_SESSIONS === $attribute && null === $subject => 'ROLE_ADMIN',
            self::VIEW_ROLES === $attribute && null === $subject => 'ROLE_ADMIN',
            self::VIEW_POLICIES === $attribute && null === $subject => 'ROLE_ADMIN',
            self::VIEW_AUDIT === $attribute && null === $subject => 'ROLE_ADMIN',
            default => null,
        };
    }
}
