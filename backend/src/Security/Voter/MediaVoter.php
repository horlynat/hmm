<?php

namespace App\Security\Voter;

use App\Entity\Media;

/**
 * Permissions sur les fichiers médias.
 *
 * - UPLOAD (pas de sujet, action globale) : Éditeur et plus.
 * - DELETE                                : Modérateur et plus.
 * - VIEW_PRIVATE                          : Manager et plus.
 */
class MediaVoter extends AbstractRoleVoter
{
    public const UPLOAD = 'MEDIA_UPLOAD';
    public const DELETE = 'MEDIA_DELETE';
    public const VIEW_PRIVATE = 'MEDIA_VIEW_PRIVATE';

    protected function getRequiredRole(string $attribute, mixed $subject): ?string
    {
        return match (true) {
            self::UPLOAD === $attribute && null === $subject => 'ROLE_EDITOR',
            self::DELETE === $attribute && $subject instanceof Media => 'ROLE_MODERATOR',
            self::VIEW_PRIVATE === $attribute && ($subject instanceof Media || null === $subject) => 'ROLE_MANAGER',
            default => null,
        };
    }
}
