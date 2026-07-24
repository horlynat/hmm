<?php

namespace App\Enum;

/**
 * Cycle de vie réel d'une demande de devis : une fois acceptée, on ne revient
 * pas en arrière vers "refusée" — au pire les travaux sont suspendus
 * temporairement, puis repris. "Refusée" reste un état terminal (suppression
 * possible mais pas obligatoire).
 */
enum QuoteStatusEnum: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case SUSPENDED = 'suspended';
    case REJECTED = 'rejected';

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::ACCEPTED => 'Acceptée',
            self::SUSPENDED => 'Suspendue',
            self::REJECTED => 'Refusée',
        };
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-yellow-500 text-black',
            self::ACCEPTED => 'bg-green-500 text-white',
            self::SUSPENDED => 'bg-orange-500 text-white',
            self::REJECTED => 'bg-red-500 text-white',
        };
    }
}
