<?php

namespace App\Enum;

enum ThemeEnum: string
{
    case LIGHT = 'light';
    case DARK = 'dark';
    case AUTO = 'auto';

    public function getLabel(): string
    {
        return match ($this) {
            self::LIGHT => 'Clair',
            self::DARK => 'Sombre',
            self::AUTO => 'Automatique (système)',
        };
    }
}
