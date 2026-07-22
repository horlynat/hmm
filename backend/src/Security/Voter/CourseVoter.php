<?php

namespace App\Security\Voter;

use App\Entity\Course;

/**
 * Permissions sur les formations (courses).
 *
 * - CREATE / EDIT : Éditeur et plus.
 * - DELETE        : Modérateur et plus.
 * - VALIDATE      : Modérateur et plus (certification, action de contrôle).
 */
class CourseVoter extends AbstractRoleVoter
{
    public const CREATE = 'COURSE_CREATE';
    public const EDIT = 'COURSE_EDIT';
    public const DELETE = 'COURSE_DELETE';
    public const VALIDATE = 'COURSE_VALIDATE';

    protected function getRequiredRole(string $attribute, mixed $subject): ?string
    {
        return match (true) {
            self::CREATE === $attribute && null === $subject => 'ROLE_EDITOR',
            self::EDIT === $attribute && $subject instanceof Course => 'ROLE_EDITOR',
            self::DELETE === $attribute && $subject instanceof Course => 'ROLE_MODERATOR',
            self::VALIDATE === $attribute && $subject instanceof Course => 'ROLE_MODERATOR',
            default => null,
        };
    }
}
