<?php

namespace App\Security\Voter;

use App\Entity\Testimonial;

/**
 * Permissions sur les témoignages.
 *
 * - VIEW / CREATE / EDIT / APPROVE / REJECT : Modérateur et plus (contenu modéré,
 *   pas d'auto-publication possible même pour un simple Éditeur).
 * - DELETE                                  : Manager et plus.
 */
class TestimonialVoter extends AbstractRoleVoter
{
    public const VIEW = 'TESTIMONIAL_VIEW';
    public const CREATE = 'TESTIMONIAL_CREATE';
    public const EDIT = 'TESTIMONIAL_EDIT';
    public const APPROVE = 'TESTIMONIAL_APPROVE';
    public const REJECT = 'TESTIMONIAL_REJECT';
    public const DELETE = 'TESTIMONIAL_DELETE';

    protected function getRequiredRole(string $attribute, mixed $subject): ?string
    {
        return match (true) {
            self::VIEW === $attribute && ($subject instanceof Testimonial || null === $subject) => 'ROLE_MODERATOR',
            self::CREATE === $attribute && null === $subject => 'ROLE_MODERATOR',
            self::EDIT === $attribute && $subject instanceof Testimonial => 'ROLE_MODERATOR',
            self::APPROVE === $attribute && $subject instanceof Testimonial => 'ROLE_MODERATOR',
            self::REJECT === $attribute && $subject instanceof Testimonial => 'ROLE_MODERATOR',
            self::DELETE === $attribute && $subject instanceof Testimonial => 'ROLE_MANAGER',
            default => null,
        };
    }
}
