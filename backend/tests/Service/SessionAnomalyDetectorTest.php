<?php

namespace App\Tests\Service;

use App\Service\SessionAnomalyDetector;
use PHPUnit\Framework\TestCase;

final class SessionAnomalyDetectorTest extends TestCase
{
    private SessionAnomalyDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new SessionAnomalyDetector();
    }

    public function testNoAnomalyWithFewerThanTwoSessions(): void
    {
        $sessions = [
            ['sessionId' => 'a', 'latitude' => 48.8566, 'longitude' => 2.3522, 'at' => new \DateTimeImmutable('2026-01-01 10:00:00')],
        ];

        self::assertSame([], $this->detector->detectImpossibleTravel($sessions));
    }

    public function testNoAnomalyForSameLocation(): void
    {
        $sessions = [
            ['sessionId' => 'a', 'latitude' => 48.8566, 'longitude' => 2.3522, 'at' => new \DateTimeImmutable('2026-01-01 10:00:00')],
            ['sessionId' => 'b', 'latitude' => 48.8566, 'longitude' => 2.3522, 'at' => new \DateTimeImmutable('2026-01-01 10:30:00')],
        ];

        self::assertSame([], $this->detector->detectImpossibleTravel($sessions));
    }

    /**
     * Paris (48.8566, 2.3522) -> New York (40.7128, -74.0060) ≈ 5 837 km.
     * En 1h, ça implique une vitesse largement au-delà de n'importe quel vol
     * commercial : doit être détecté.
     */
    public function testDetectsImpossibleTravelBetweenDistantConcurrentSessions(): void
    {
        $sessions = [
            ['sessionId' => 'paris', 'latitude' => 48.8566, 'longitude' => 2.3522, 'at' => new \DateTimeImmutable('2026-01-01 10:00:00')],
            ['sessionId' => 'ny', 'latitude' => 40.7128, 'longitude' => -74.0060, 'at' => new \DateTimeImmutable('2026-01-01 11:00:00')],
        ];

        $anomalies = $this->detector->detectImpossibleTravel($sessions);

        self::assertCount(1, $anomalies);
        self::assertSame('paris', $anomalies[0]['sessionIdA']);
        self::assertSame('ny', $anomalies[0]['sessionIdB']);
        self::assertGreaterThan(5000.0, $anomalies[0]['distanceKm']);
        self::assertGreaterThan(900.0, $anomalies[0]['impliedSpeedKmh']);
    }

    /**
     * Même trajet Paris -> New York, mais étalé sur 24h : un vol long-courrier
     * couvre largement cette distance dans ce délai, ce n'est plus une anomalie.
     */
    public function testNoAnomalyWhenTravelTimeIsPlausible(): void
    {
        $sessions = [
            ['sessionId' => 'paris', 'latitude' => 48.8566, 'longitude' => 2.3522, 'at' => new \DateTimeImmutable('2026-01-01 10:00:00')],
            ['sessionId' => 'ny', 'latitude' => 40.7128, 'longitude' => -74.0060, 'at' => new \DateTimeImmutable('2026-01-02 10:00:00')],
        ];

        self::assertSame([], $this->detector->detectImpossibleTravel($sessions));
    }

    /**
     * Deux connexions quasi simultanées (< 60s) depuis des points distants :
     * bruit probable de géolocalisation IP plutôt qu'un vrai déplacement,
     * volontairement ignoré (cf. MIN_INTERVAL_SECONDS).
     */
    public function testIgnoresSessionsWithinMinimumInterval(): void
    {
        $sessions = [
            ['sessionId' => 'a', 'latitude' => 48.8566, 'longitude' => 2.3522, 'at' => new \DateTimeImmutable('2026-01-01 10:00:00')],
            ['sessionId' => 'b', 'latitude' => 40.7128, 'longitude' => -74.0060, 'at' => new \DateTimeImmutable('2026-01-01 10:00:30')],
        ];

        self::assertSame([], $this->detector->detectImpossibleTravel($sessions));
    }

    public function testIgnoresSessionsWithoutCoordinates(): void
    {
        $sessions = [
            ['sessionId' => 'a', 'latitude' => null, 'longitude' => null, 'at' => new \DateTimeImmutable('2026-01-01 10:00:00')],
            ['sessionId' => 'b', 'latitude' => 40.7128, 'longitude' => -74.0060, 'at' => new \DateTimeImmutable('2026-01-01 10:05:00')],
        ];

        self::assertSame([], $this->detector->detectImpossibleTravel($sessions));
    }

    public function testSelectSessionsExceedingLimitReturnsEmptyWhenUnderLimit(): void
    {
        $sessions = ['s1', 's2', 's3'];

        self::assertSame([], $this->detector->selectSessionsExceedingLimit($sessions, 5));
    }

    public function testSelectSessionsExceedingLimitReturnsEmptyWhenExactlyAtLimit(): void
    {
        $sessions = ['s1', 's2', 's3', 's4', 's5'];

        self::assertSame([], $this->detector->selectSessionsExceedingLimit($sessions, 5));
    }

    public function testSelectSessionsExceedingLimitReturnsOldestFirst(): void
    {
        // Convention de l'appelant : la liste est déjà triée de la plus
        // ancienne à la plus récente — ne jamais évincer la plus récente.
        $sessions = ['oldest', 's2', 's3', 's4', 'newest'];

        self::assertSame(['oldest'], $this->detector->selectSessionsExceedingLimit($sessions, 4));
    }

    public function testSelectSessionsExceedingLimitUsesDefaultConstant(): void
    {
        $sessions = array_fill(0, SessionAnomalyDetector::DEFAULT_MAX_CONCURRENT_SESSIONS + 2, 'x');

        self::assertCount(2, $this->detector->selectSessionsExceedingLimit($sessions));
    }
}
