<?php

namespace App\Tests\Enum;

use App\Enum\ProjectStatusEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProjectStatusEnumTest extends TestCase
{
    public function testCompletedCanBeReopenedToInProgress(): void
    {
        // Régression : un projet "terminé" doit rester corrigible (contenu mal
        // présenté au portail) sans modification SQL directe.
        self::assertTrue(ProjectStatusEnum::COMPLETED->canTransitionTo(ProjectStatusEnum::IN_PROGRESS));
    }

    /**
     * @return iterable<string, array{ProjectStatusEnum}>
     */
    public static function otherStatusesProvider(): iterable
    {
        yield 'à venir' => [ProjectStatusEnum::UPCOMING];
        yield 'suspendu' => [ProjectStatusEnum::SUSPENDED];
        yield 'collaboration' => [ProjectStatusEnum::COLLABORATION];
        yield 'terminé' => [ProjectStatusEnum::COMPLETED];
    }

    #[DataProvider('otherStatusesProvider')]
    public function testCompletedCannotTransitionToAnythingElse(ProjectStatusEnum $target): void
    {
        // La réouverture reste ciblée : uniquement vers IN_PROGRESS, pas un
        // raccourci vers n'importe quel autre statut.
        self::assertFalse(ProjectStatusEnum::COMPLETED->canTransitionTo($target));
    }

    public function testUpcomingCannotTransitionToCompletedDirectly(): void
    {
        // Garde-fou existant, non touché par l'ajout de la réouverture.
        self::assertFalse(ProjectStatusEnum::UPCOMING->canTransitionTo(ProjectStatusEnum::COMPLETED));
    }
}
