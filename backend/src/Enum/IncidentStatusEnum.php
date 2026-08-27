<?php

namespace App\Enum;

/**
 * Cycle de vie d'un incident (App\Entity\Incident) : OPEN à la création,
 * MONITORING une fois un correctif appliqué mais pas encore confirmé stable
 * dans la durée, RESOLVED une fois confirmé. Pas de transition interdite
 * (contrairement à ProjectStatusEnum) : un incident MONITORING peut
 * redevenir OPEN si le problème réapparaît — c'est justement le genre de
 * récurrence que cet espace sert à repérer.
 */
enum IncidentStatusEnum: string
{
    case OPEN = 'open';
    case MONITORING = 'monitoring';
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
            self::MONITORING => 'Sous surveillance',
            self::RESOLVED => 'Résolu',
        };
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::OPEN => 'bg-red-500 text-white',
            self::MONITORING => 'bg-yellow-500 text-black',
            self::RESOLVED => 'bg-green-500 text-white',
        };
    }
}
