<?php

namespace App\Security\Voter;

use App\Entity\NewsletterSubscriber;

/**
 * Permissions sur les abonnés newsletter.
 *
 * - VIEW   : Modérateur et plus (même sensibilité que les messages de
 *   contact — des adresses e-mail de visiteurs, cf. ContactVoter).
 * - DELETE : Manager et plus (suppression définitive d'un abonné).
 */
class NewsletterVoter extends AbstractRoleVoter
{
    public const VIEW = 'NEWSLETTER_VIEW';
    public const DELETE = 'NEWSLETTER_DELETE';

    protected function getRequiredRole(string $attribute, mixed $subject): ?string
    {
        return match (true) {
            self::VIEW === $attribute && ($subject instanceof NewsletterSubscriber || null === $subject) => 'ROLE_MODERATOR',
            self::DELETE === $attribute && $subject instanceof NewsletterSubscriber => 'ROLE_MANAGER',
            default => null,
        };
    }
}
