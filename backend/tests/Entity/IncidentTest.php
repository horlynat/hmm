<?php

namespace App\Tests\Entity;

use App\Entity\Incident;
use App\Enum\IncidentStatusEnum;
use PHPUnit\Framework\TestCase;

final class IncidentTest extends TestCase
{
    public function testResolvingSetsResolvedAtAutomatically(): void
    {
        $incident = new Incident();
        self::assertNull($incident->getResolvedAt());

        $incident->setStatus(IncidentStatusEnum::RESOLVED);

        self::assertNotNull($incident->getResolvedAt());
        self::assertTrue($incident->isOpen() === false);
    }

    public function testReopeningClearsResolvedAt(): void
    {
        $incident = new Incident();
        $incident->setStatus(IncidentStatusEnum::RESOLVED);
        self::assertNotNull($incident->getResolvedAt());

        // Régression : un incident qui réapparaît (cf. docblock de l'entité)
        // ne doit plus afficher une ancienne date de résolution périmée.
        $incident->setStatus(IncidentStatusEnum::OPEN);

        self::assertNull($incident->getResolvedAt());
        self::assertTrue($incident->isOpen());
    }

    public function testResolvingTwiceDoesNotChangeTheOriginalResolvedAtTimestamp(): void
    {
        $incident = new Incident();
        $incident->setStatus(IncidentStatusEnum::RESOLVED);
        $firstResolvedAt = $incident->getResolvedAt();

        $incident->setStatus(IncidentStatusEnum::RESOLVED);

        self::assertSame($firstResolvedAt, $incident->getResolvedAt());
    }

    public function testMonitoringIsConsideredOpen(): void
    {
        $incident = new Incident();
        $incident->setStatus(IncidentStatusEnum::MONITORING);

        self::assertTrue($incident->isOpen());
    }
}
