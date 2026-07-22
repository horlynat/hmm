<?php

namespace App\Security\Voter;

use App\Entity\QuoteRequest;

/**
 * Permissions sur les demandes de devis.
 *
 * - VIEW / EDIT / DELETE / APPROVE / REJECT : Manager et plus (impact commercial direct).
 * - CONVERT (transformation en projet)      : Administrateur et plus.
 */
class QuoteVoter extends AbstractRoleVoter
{
    public const VIEW = 'QUOTE_VIEW';
    public const EDIT = 'QUOTE_EDIT';
    public const DELETE = 'QUOTE_DELETE';
    public const APPROVE = 'QUOTE_APPROVE';
    public const REJECT = 'QUOTE_REJECT';
    public const CONVERT = 'QUOTE_CONVERT';

    protected function getRequiredRole(string $attribute, mixed $subject): ?string
    {
        return match (true) {
            self::VIEW === $attribute && ($subject instanceof QuoteRequest || null === $subject) => 'ROLE_MANAGER',
            self::EDIT === $attribute && $subject instanceof QuoteRequest => 'ROLE_MANAGER',
            self::DELETE === $attribute && $subject instanceof QuoteRequest => 'ROLE_MANAGER',
            self::APPROVE === $attribute && $subject instanceof QuoteRequest => 'ROLE_MANAGER',
            self::REJECT === $attribute && $subject instanceof QuoteRequest => 'ROLE_MANAGER',
            self::CONVERT === $attribute && $subject instanceof QuoteRequest => 'ROLE_ADMIN',
            default => null,
        };
    }
}
