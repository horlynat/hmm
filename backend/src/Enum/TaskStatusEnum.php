<?php

namespace App\Enum;

/**
 * Statut d'une tâche/jalon de projet. DONE compte dans l'avancement du projet.
 */
enum TaskStatusEnum: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';
    case BLOCKED = 'blocked';

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::TODO => 'À faire',
            self::IN_PROGRESS => 'En cours',
            self::DONE => 'Terminée',
            self::BLOCKED => 'Bloquée',
        };
    }

    /** Variante du composant <twig:Badge>. */
    public function getVariant(): string
    {
        return match ($this) {
            self::TODO => 'neutral',
            self::IN_PROGRESS => 'info',
            self::DONE => 'success',
            self::BLOCKED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::TODO => 'ti-circle',
            self::IN_PROGRESS => 'ti-progress',
            self::DONE => 'ti-circle-check',
            self::BLOCKED => 'ti-ban',
        };
    }

    public function isDone(): bool
    {
        return self::DONE === $this;
    }
}
