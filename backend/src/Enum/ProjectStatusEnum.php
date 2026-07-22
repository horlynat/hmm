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
        self::COMPLETED->value => [],
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