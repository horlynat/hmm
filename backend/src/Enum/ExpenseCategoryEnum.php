<?php

namespace App\Enum;

/**
 * Nature d'une dépense de projet — pour la ventilation et le suivi analytique.
 */
enum ExpenseCategoryEnum: string
{
    case LABOR = 'labor';
    case MATERIAL = 'material';
    case SUBCONTRACTING = 'subcontracting';
    case SOFTWARE = 'software';
    case TRAVEL = 'travel';
    case OTHER = 'other';

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::LABOR => 'Main d\'œuvre',
            self::MATERIAL => 'Matériel',
            self::SUBCONTRACTING => 'Sous-traitance',
            self::SOFTWARE => 'Logiciel / licence',
            self::TRAVEL => 'Déplacement',
            self::OTHER => 'Autre',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::LABOR => 'ti-users',
            self::MATERIAL => 'ti-package',
            self::SUBCONTRACTING => 'ti-briefcase',
            self::SOFTWARE => 'ti-license',
            self::TRAVEL => 'ti-plane',
            self::OTHER => 'ti-dots',
        };
    }
}
