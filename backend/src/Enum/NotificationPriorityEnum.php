<?php

namespace App\Enum;

/**
 * Niveaux d'importance repris de config/packages/notifier.yaml (channel_policy).
 * Les valeurs doivent rester alignées avec les clés utilisées là-bas.
 */
enum NotificationPriorityEnum: string
{
    case URGENT = 'urgent';
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';

    public function getLabel(): string
    {
        return match ($this) {
            self::URGENT => 'Urgente',
            self::HIGH => 'Haute',
            self::MEDIUM => 'Moyenne',
            self::LOW => 'Basse',
        };
    }

    /** @return array<int, self> */
    public static function all(): array
    {
        return self::cases();
    }
}
