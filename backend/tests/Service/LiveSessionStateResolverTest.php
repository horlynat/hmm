<?php

namespace App\Tests\Service;

use App\Service\LiveSessionStateResolver;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;

/**
 * sess_lifetime est un TIMESTAMP ABSOLU d'expiration (cf. le commentaire de
 * classe de LiveSessionStateResolver et PdoSessionHandler côté vendor), pas
 * une durée — ces tests figent ce comportement pour ne jamais réintroduire
 * l'ancien bug (sess_time + sess_lifetime).
 */
final class LiveSessionStateResolverTest extends TestCase
{
    public function testResolveAllMarksFutureLifetimeAsActive(): void
    {
        $now = time();
        $result = $this->createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['sess_id' => 'sess-active', 'sess_time' => $now - 30, 'sess_lifetime' => $now + 1440],
        ]);

        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willReturn($result);

        $resolver = new LiveSessionStateResolver($connection);
        $states = $resolver->resolveAll();

        self::assertTrue($states['sess-active']['active']);
        self::assertSame($now - 30, $states['sess-active']['lastActivityAt']);
    }

    public function testResolveAllMarksPastLifetimeAsExpired(): void
    {
        $now = time();
        $result = $this->createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['sess_id' => 'sess-expired', 'sess_time' => $now - 5000, 'sess_lifetime' => $now - 100],
        ]);

        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willReturn($result);

        $resolver = new LiveSessionStateResolver($connection);
        $states = $resolver->resolveAll();

        self::assertFalse($states['sess-expired']['active']);
    }

    /**
     * Régression : l'ancien calcul (sess_time + sess_lifetime >= now()) aurait
     * classé CETTE ligne "active" alors que sess_lifetime (le vrai timestamp
     * d'expiration) est déjà dans le passé — sess_time seul, très ancien,
     * suffit à faire dépasser `now()` une fois additionné à sess_lifetime.
     */
    public function testDoesNotReproduceTheAdditionBug(): void
    {
        $now = time();
        $result = $this->createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['sess_id' => 'sess-old', 'sess_time' => $now - 100000, 'sess_lifetime' => $now - 50],
        ]);

        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willReturn($result);

        $resolver = new LiveSessionStateResolver($connection);
        $states = $resolver->resolveAll();

        self::assertFalse($states['sess-old']['active']);
    }

    public function testIsActiveReturnsFalseWhenSessionRowMissing(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willReturn($this->createConfiguredStub(Result::class, ['fetchOne' => false]));

        $resolver = new LiveSessionStateResolver($connection);

        self::assertFalse($resolver->isActive('unknown-session'));
    }

    public function testIsActiveReturnsTrueForFutureLifetime(): void
    {
        $now = time();
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willReturn($this->createConfiguredStub(Result::class, ['fetchOne' => $now + 500]));

        $resolver = new LiveSessionStateResolver($connection);

        self::assertTrue($resolver->isActive('sess-1'));
    }
}
