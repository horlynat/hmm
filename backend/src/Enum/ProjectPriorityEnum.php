<?php

namespace App\Enum;

enum ProjectPriorityEnum: string
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
        return match($this) {
            self::LOW => 'Basse',
            self::MEDIUM => 'Moyenne',
            self::HIGH => 'Haute',
            self::CRITICAL => '🚨 Critique',
        };
    }

    public function getBadgeClass(): string
    {
        return match($this) {
            self::LOW => 'bg-gray-100 text-gray-800',
            self::MEDIUM => 'bg-blue-100 text-blue-800',
            self::HIGH => 'bg-orange-100 text-orange-800',
            self::CRITICAL => 'bg-red-100 text-red-800 animate-pulse',
        };
    }
}