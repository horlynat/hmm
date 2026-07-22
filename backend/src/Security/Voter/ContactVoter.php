<?php

namespace App\Security\Voter;

use App\Entity\ContactMessage;

/**
 * Permissions sur les messages de contact.
 *
 * - VIEW / REPLY / ARCHIVE / MARK_SPAM : Modérateur et plus.
 * - DELETE                             : Manager et plus (suppression définitive).
 */
class ContactVoter extends AbstractRoleVoter
{
    public const VIEW = 'CONTACT_VIEW';
    public const REPLY = 'CONTACT_REPLY';
    public const DELETE = 'CONTACT_DELETE';
    public const ARCHIVE = 'CONTACT_ARCHIVE';
    public const MARK_SPAM = 'CONTACT_MARK_SPAM';

    protected function getRequiredRole(string $attribute, mixed $subject): ?string
    {
        return match (true) {
            self::VIEW === $attribute && ($subject instanceof ContactMessage || null === $subject) => 'ROLE_MODERATOR',
            self::REPLY === $attribute && $subject instanceof ContactMessage => 'ROLE_MODERATOR',
            self::ARCHIVE === $attribute && $subject instanceof ContactMessage => 'ROLE_MODERATOR',
            self::MARK_SPAM === $attribute && $subject instanceof ContactMessage => 'ROLE_MODERATOR',
            self::DELETE === $attribute && $subject instanceof ContactMessage => 'ROLE_MANAGER',
            default => null,
        };
    }
}
