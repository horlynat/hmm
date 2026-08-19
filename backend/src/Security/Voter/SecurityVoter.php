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
    public const MANAGE_SESSIONS = 'SECURITY_MANAGE_SESSIONS';
    public const VIEW_ROLES = 'SECURITY_VIEW_ROLES';
    public const VIEW_POLICIES = 'SECURITY_VIEW_POLICIES';
    public const VIEW_AUDIT = 'SECURITY_VIEW_AUDIT';
    public const MANAGE_IP_BLOCKS = 'SECURITY_MANAGE_IP_BLOCKS';
    public const MANAGE_LOGS = 'SECURITY_MANAGE_LOGS';

    /**
     * Éditer le catalogue de permissions dynamiques (PermissionRegistry) —
     * volontairement au-dessus du seuil ROLE_ADMIN du reste de cette classe :
     * cette action peut changer QUI peut faire QUOI ailleurs dans l'app, donc
     * un rang de confiance strictement supérieur à celui qu'elle gouverne.
     * Rappel : ce code lui-même (préfixe SECURITY_) n'est jamais consultable
     * dynamiquement, cf. PermissionRegistry::NON_OVERRIDABLE_PREFIXES —
     * personne ne peut s'auto-attribuer ce droit en le modifiant en base.
     */
    public const MANAGE_PERMISSIONS = 'SECURITY_MANAGE_PERMISSIONS';

    protected function getRequiredRole(string $attribute, mixed $subject): ?string
    {
        return match (true) {
            self::FORCE_LOGOUT === $attribute && $subject instanceof User => 'ROLE_ADMIN',
            self::VIEW_LOGS === $attribute && null === $subject => 'ROLE_ADMIN',
            self::MANAGE_2FA === $attribute && (null === $subject || $subject instanceof User) => 'ROLE_ADMIN',
            self::MANAGE_SESSIONS === $attribute && null === $subject => 'ROLE_ADMIN',
            self::VIEW_ROLES === $attribute && null === $subject => 'ROLE_ADMIN',
            self::VIEW_POLICIES === $attribute && null === $subject => 'ROLE_ADMIN',
            self::VIEW_AUDIT === $attribute && null === $subject => 'ROLE_ADMIN',
            self::MANAGE_IP_BLOCKS === $attribute && null === $subject => 'ROLE_ADMIN',
            self::MANAGE_LOGS === $attribute && null === $subject => 'ROLE_ADMIN',
            self::MANAGE_PERMISSIONS === $attribute && null === $subject => 'ROLE_SUPER_ADMIN',
            default => null,
        };
    }
}
