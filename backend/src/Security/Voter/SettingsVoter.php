<?php

namespace App\Security\Voter;

/**
 * Permissions sur la section Paramètres du back-office (Configuration,
 * Notifications, Intégrations, Sauvegardes).
 *
 * Consultation et gestion courante réservées à ROLE_ADMIN, comme le reste des
 * zones sensibles (cf. SecurityVoter). Suppression et restauration de
 * sauvegarde sont réservées à ROLE_SUPER_ADMIN : une restauration écrase
 * l'intégralité de la base de données, ce n'est pas une action de modération
 * courante.
 */
class SettingsVoter extends AbstractRoleVoter
{
    public const VIEW_CONFIG = 'SETTINGS_VIEW_CONFIG';
    public const MANAGE_CONFIG = 'SETTINGS_MANAGE_CONFIG';

    public const VIEW_NOTIFICATIONS = 'SETTINGS_VIEW_NOTIFICATIONS';
    public const MANAGE_NOTIFICATIONS = 'SETTINGS_MANAGE_NOTIFICATIONS';

    public const VIEW_INTEGRATIONS = 'SETTINGS_VIEW_INTEGRATIONS';
    public const MANAGE_INTEGRATIONS = 'SETTINGS_MANAGE_INTEGRATIONS';

    public const VIEW_BACKUPS = 'SETTINGS_VIEW_BACKUPS';
    public const CREATE_BACKUP = 'SETTINGS_CREATE_BACKUP';
    public const DOWNLOAD_BACKUP = 'SETTINGS_DOWNLOAD_BACKUP';
    public const DELETE_BACKUP = 'SETTINGS_DELETE_BACKUP';
    public const RESTORE_BACKUP = 'SETTINGS_RESTORE_BACKUP';

    private const ADMIN_ATTRIBUTES = [
        self::VIEW_CONFIG,
        self::MANAGE_CONFIG,
        self::VIEW_NOTIFICATIONS,
        self::MANAGE_NOTIFICATIONS,
        self::VIEW_INTEGRATIONS,
        self::MANAGE_INTEGRATIONS,
        self::VIEW_BACKUPS,
        self::CREATE_BACKUP,
        self::DOWNLOAD_BACKUP,
    ];

    private const SUPER_ADMIN_ATTRIBUTES = [
        self::DELETE_BACKUP,
        self::RESTORE_BACKUP,
    ];

    protected function getRequiredRole(string $attribute, mixed $subject): ?string
    {
        if (null !== $subject) {
            return null;
        }

        return match (true) {
            \in_array($attribute, self::ADMIN_ATTRIBUTES, true) => 'ROLE_ADMIN',
            \in_array($attribute, self::SUPER_ADMIN_ATTRIBUTES, true) => 'ROLE_SUPER_ADMIN',
            default => null,
        };
    }
}
