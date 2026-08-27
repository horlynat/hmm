<?php

namespace App\Enum;

/**
 * Gravité d'un incident (App\Entity\Incident) — indépendante du statut
 * (un incident CRITICAL peut être RESOLVED, un incident LOW peut rester OPEN
 * longtemps s'il n'est pas urgent).
 */
enum IncidentSeverityEnum: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::LOW => 'Faible',
            self::MEDIUM => 'Moyenne',
            self::HIGH => 'Élevée',
            self::CRITICAL => 'Critique',
        };
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::LOW => 'bg-gray-300 text-black',
            self::MEDIUM => 'bg-yellow-500 text-black',
            self::HIGH => 'bg-orange-500 text-white',
            self::CRITICAL => 'bg-red-600 text-white',
        };
    }
}
