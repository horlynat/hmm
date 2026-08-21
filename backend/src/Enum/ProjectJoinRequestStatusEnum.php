<?php

namespace App\Enum;

/**
 * Statut d'une demande d'auto-association freelance ↔ projet "à venir"
 * (App\Entity\ProjectJoinRequest). Une demande "en attente" ne donne AUCUN
 * accès au projet — seule son approbation par un admin ajoute réellement le
 * freelance à Project::$collaborators (cf. AdminProjectController::approveJoinRequest).
 */
enum ProjectJoinRequestStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'En attente de validation',
            self::APPROVED => 'Validée',
            self::REJECTED => 'Refusée',
        };
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-amber-100 text-amber-800',
            self::APPROVED => 'bg-green-500 text-white',
            self::REJECTED => 'bg-red-500 text-white',
        };
    }
}
