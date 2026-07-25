<?php

namespace App\Enum;

/**
 * Cycle de vie d'une dépense de projet.
 * Seules les dépenses APPROVED impactent le budget dépensé (`Project::spent`).
 */
enum ExpenseStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::APPROVED => 'Approuvée',
            self::REJECTED => 'Refusée',
        };
    }

    /** Variante du composant <twig:Badge>. */
    public function getVariant(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'ti-clock',
            self::APPROVED => 'ti-circle-check',
            self::REJECTED => 'ti-circle-x',
        };
    }
}
