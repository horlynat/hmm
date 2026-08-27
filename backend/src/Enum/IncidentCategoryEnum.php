<?php

namespace App\Enum;

/**
 * Catégorie d'un incident (App\Entity\Incident) — sert avant tout à repérer
 * les récurrences (cf. IncidentRepository::countByCategory) : un même type
 * d'incident qui revient plusieurs fois est un signal qu'un correctif
 * ponctuel n'a pas traité la cause racine.
 */
enum IncidentCategoryEnum: string
{
    case AUTHENTICATION = 'authentication';
    case DATA_LOSS = 'data_loss';
    case DEPLOYMENT = 'deployment';
    case INFRASTRUCTURE = 'infrastructure';
    case SECURITY = 'security';
    case PERFORMANCE = 'performance';
    case OTHER = 'other';

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::AUTHENTICATION => 'Authentification',
            self::DATA_LOSS => 'Perte de données',
            self::DEPLOYMENT => 'Déploiement',
            self::INFRASTRUCTURE => 'Infrastructure',
            self::SECURITY => 'Sécurité',
            self::PERFORMANCE => 'Performance',
            self::OTHER => 'Autre',
        };
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::AUTHENTICATION => 'bg-purple-500 text-white',
            self::DATA_LOSS => 'bg-red-600 text-white',
            self::DEPLOYMENT => 'bg-blue-500 text-white',
            self::INFRASTRUCTURE => 'bg-slate-500 text-white',
            self::SECURITY => 'bg-orange-500 text-white',
            self::PERFORMANCE => 'bg-teal-500 text-white',
            self::OTHER => 'bg-gray-400 text-black',
        };
    }
}
