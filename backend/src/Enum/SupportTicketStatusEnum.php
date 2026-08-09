<?php

namespace App\Enum;

/**
 * Cycle de vie d'un ticket support : création -> OPEN ; toute réponse admin
 * -> IN_PROGRESS (quel que soit l'état précédent, y compris RESOLVED si
 * l'admin relance) ; réponse invitée sur un ticket RESOLVED -> réouverture
 * automatique vers IN_PROGRESS (cf. SupportTicket::reopenIfResolved()) ;
 * action explicite "Marquer résolu" uniquement depuis OPEN/IN_PROGRESS.
 */
enum SupportTicketStatusEnum: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::OPEN => 'Ouvert',
            self::IN_PROGRESS => 'En cours',
            self::RESOLVED => 'Résolu',
        };
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::OPEN => 'bg-blue-500 text-white',
            self::IN_PROGRESS => 'bg-yellow-500 text-black',
            self::RESOLVED => 'bg-green-500 text-white',
        };
    }
}
