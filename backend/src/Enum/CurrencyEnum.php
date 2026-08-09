<?php

namespace App\Enum;

/**
 * Devises proposées au sélecteur d'affichage (dashboard admin + espace
 * client). Ne contraint QUE la devise cible : les montants stockés
 * (Invoice::$currency notamment) peuvent rester dans n'importe quel code ISO
 * existant (ex: GBP) — CurrencyConversionService::convert() accepte
 * n'importe quelle devise source, seule la devise cible du sélecteur est
 * restreinte à ces cas.
 */
enum CurrencyEnum: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case XAF = 'XAF';

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::USD => 'Dollar américain (USD)',
            self::EUR => 'Euro (EUR)',
            self::XAF => 'Franc CFA (XAF)',
        };
    }

    public function getSymbol(): string
    {
        return match ($this) {
            self::USD => '$',
            self::EUR => '€',
            self::XAF => 'FCFA',
        };
    }
}
