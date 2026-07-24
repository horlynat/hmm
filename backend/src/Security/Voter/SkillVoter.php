<?php

namespace App\Security\Voter;

use App\Entity\Skill;

/**
 * Permissions sur les compétences (skills).
 *
 * - CREATE / EDIT : Éditeur et plus.
 * - DELETE        : Modérateur et plus.
 */
class SkillVoter extends AbstractRoleVoter
{
    public const CREATE = 'SKILL_CREATE';
    public const EDIT = 'SKILL_EDIT';
    public const DELETE = 'SKILL_DELETE';

    protected function getRequiredRole(string $attribute, mixed $subject): ?string
    {
        return match (true) {
            self::CREATE === $attribute && null === $subject => 'ROLE_EDITOR',
            self::EDIT === $attribute && $subject instanceof Skill => 'ROLE_EDITOR',
            self::DELETE === $attribute && $subject instanceof Skill => 'ROLE_MODERATOR',
            default => null,
        };
    }
}
