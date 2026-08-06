<?php

namespace App\Enum;

enum ContactMessageStatusEnum: string
{
    case NEW = 'nouveau';
    case READ = 'lu';
    case ARCHIVED = 'archive';

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::NEW => 'Nouveau',
            self::READ => 'Lu',
            self::ARCHIVED => 'Archivé',
        };
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::NEW => 'bg-blue-500 text-white',
            self::READ => 'bg-gray-400 text-white',
            self::ARCHIVED => 'bg-slate-700 text-white',
        };
    }
}
