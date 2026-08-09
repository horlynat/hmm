<?php

namespace App\Security\Voter;

use App\Entity\SupportTicket;

/**
 * Permissions sur les tickets de support.
 *
 * - VIEW / REPLY / RESOLVE : Modérateur et plus (correspondance client, même
 *   niveau que ContactVoter).
 * - DELETE                 : Manager et plus (suppression définitive).
 */
class SupportTicketVoter extends AbstractRoleVoter
{
    public const VIEW = 'SUPPORT_TICKET_VIEW';
    public const REPLY = 'SUPPORT_TICKET_REPLY';
    public const RESOLVE = 'SUPPORT_TICKET_RESOLVE';
    public const DELETE = 'SUPPORT_TICKET_DELETE';

    protected function getRequiredRole(string $attribute, mixed $subject): ?string
    {
        return match (true) {
            self::VIEW === $attribute && ($subject instanceof SupportTicket || null === $subject) => 'ROLE_MODERATOR',
            self::REPLY === $attribute && $subject instanceof SupportTicket => 'ROLE_MODERATOR',
            self::RESOLVE === $attribute && $subject instanceof SupportTicket => 'ROLE_MODERATOR',
            self::DELETE === $attribute && $subject instanceof SupportTicket => 'ROLE_MANAGER',
            default => null,
        };
    }
}
