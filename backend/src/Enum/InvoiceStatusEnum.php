<?php

namespace App\Enum;

/**
 * Cycle de vie d'une facture. Pas d'état "overdue" stocké : une facture
 * PENDING dont l'échéance est dépassée est calculée à la volée (cf.
 * Invoice::isOverdue()), comme Project::isPastDeadline().
 */
enum InvoiceStatusEnum: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'En attente de paiement',
            self::PAID => 'Payée',
            self::CANCELLED => 'Annulée',
        };
    }

    /** Variante du composant <twig:Badge>. */
    public function getVariant(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PAID => 'success',
            self::CANCELLED => 'neutral',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'ti-clock',
            self::PAID => 'ti-circle-check',
            self::CANCELLED => 'ti-ban',
        };
    }
}
