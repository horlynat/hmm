<?php

namespace App\Enum;

/**
 * Classification des formations (App\Entity\Course) affichées publiquement
 * sous "Qualifications et certifications" — permet au frontend de les
 * regrouper par type plutôt que de tout lister à plat.
 */
enum CourseTypeEnum: string
{
    case DIPLOMA = 'diplome';
    case CERTIFICATION = 'certification';
    case TRAINING = 'formation';

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_map(static fn (self $t) => $t->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::DIPLOMA => 'Diplôme',
            self::CERTIFICATION => 'Certification',
            self::TRAINING => 'Formation',
        };
    }
}
