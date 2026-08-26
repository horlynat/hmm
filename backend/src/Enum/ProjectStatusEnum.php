<?php

namespace App\Enum;

enum ProjectStatusEnum: string
{
    case UPCOMING = 'a_venir';
    case IN_PROGRESS = 'en_cours';
    case SUSPENDED = 'suspendu';
    case COLLABORATION = 'collaboration';
    case COMPLETED = 'termine';

    private const VALID_TRANSITIONS = [
        self::UPCOMING->value => [self::IN_PROGRESS->value, self::SUSPENDED->value],
        self::IN_PROGRESS->value => [self::COMPLETED->value, self::SUSPENDED->value, self::COLLABORATION->value],
        self::SUSPENDED->value => [self::IN_PROGRESS->value, self::COMPLETED->value],
        self::COLLABORATION->value => [self::IN_PROGRESS->value, self::COMPLETED->value],
        // Un projet "terminé" n'est pas une erreur zéro : le contenu publié côté
        // portail (titre, description, médias, lien) peut être mal présenté même
        // après clôture, et rien ne doit obliger à passer par une modification SQL
        // directe pour le corriger. Décision produit du 26/08/2026 : autoriser
        // explicitement la réouverture (COMPLETED -> IN_PROGRESS), réservée à
        // ROLE_ADMIN via ProjectVoter::CHANGE_STATUS (cf. AdminProjectController::
        // changeStatus, déjà journalisé par Project::logStatusChange). Ne pas
        // restreindre cette réouverture aux seuls champs de présentation : une
        // fois rouverte, l'édition redevient possible sur TOUT le projet (budget,
        // dépenses, tâches inclus, cf. ProjectVoter::isProjectActive) — le choix
        // assumé est qu'une erreur peut venir de n'importe quel champ, pas
        // seulement de l'affichage public. Reclôturer repasse par les mêmes
        // garde-fous qu'une clôture normale (dépenses/tâches en attente, cf.
        // AdminProjectController::changeStatus).
        self::COMPLETED->value => [self::IN_PROGRESS->value],
    ];

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match($this) {
            self::UPCOMING => 'À venir',
            self::IN_PROGRESS => 'En cours',
            self::SUSPENDED => 'Suspendu',
            self::COLLABORATION => 'Collaboration',
            self::COMPLETED => 'Terminé',
        };
    }

    public function getBadgeClass(): string
    {
        return match($this) {
            self::UPCOMING => 'bg-yellow-500 text-black',
            self::IN_PROGRESS => 'bg-blue-500 text-white',
            self::SUSPENDED => 'bg-red-500 text-white',
            self::COLLABORATION => 'bg-purple-500 text-white',
            self::COMPLETED => 'bg-green-500 text-white',
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus->value, self::VALID_TRANSITIONS[$this->value]);
    }
}