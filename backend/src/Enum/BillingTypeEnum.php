<?php

namespace App\Enum;

enum BillingTypeEnum: string
{
    case FIXED = 'fixed';
    case TIME_AND_MATERIALS = 'time_and_materials';
    case RETAINER = 'retainer';

    /**
     * Libellé lisible pour l'interface utilisateur.
     */
    public function getLabel(): string
    {
        return match($this) {
            self::FIXED => 'Forfait',
            self::TIME_AND_MATERIALS => 'Régie / Temps passé',
            self::RETAINER => 'Abonnement / Récurrence',
        };
    }

    /**
     * Classe CSS pour les badges du tableau de bord.
     */
    public function getBadgeClass(): string
    {
        return match($this) {
            self::FIXED => 'bg-info text-dark',
            self::TIME_AND_MATERIALS => 'bg-warning text-dark',
            self::RETAINER => 'bg-primary text-white',
        };
    }
}