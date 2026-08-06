<?php

namespace App\Enum;

enum BudgetStatusEnum: string
{
    case OK = 'ok';
    case LOW = 'low';
    case OVER = 'over';
    case PROFITABLE = 'profitable';

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match($this) {
            self::OK => 'Sain (>10% restant)',
            self::LOW => 'Alerte Seuil (<10%)',
            self::OVER => 'Dépassement Critique',
            self::PROFITABLE => 'Rentable & Terminé',
        };
    }
}