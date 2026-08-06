<?php

namespace App\Security\Voter;

use App\Entity\Course;

/**
 * Permissions sur les formations (courses).
 *
 * - CREATE / EDIT : Éditeur et plus.
 * - DELETE        : Modérateur et plus.
 */
class CourseVoter extends AbstractRoleVoter
{
    public const CREATE = 'COURSE_CREATE';
    public const EDIT = 'COURSE_EDIT';
    public const DELETE = 'COURSE_DELETE';

    protected function getRequiredRole(string $attribute, mixed $subject): ?string
    {
        return match (true) {
            self::CREATE === $attribute && null === $subject => 'ROLE_EDITOR',
            self::EDIT === $attribute && $subject instanceof Course => 'ROLE_EDITOR',
            self::DELETE === $attribute && $subject instanceof Course => 'ROLE_MODERATOR',
            default => null,
        };
    }
}
