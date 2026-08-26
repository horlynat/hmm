<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\UserSession;
use App\Service\UserSessionRevoker;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class UserSessionRevokerTest extends TestCase
{
    private function createUserSession(string $email = 'jane@example.com', string $sessionId = 'sess-123'): UserSession
    {
        $user = new User();
        $user->setEmail($email);

        return new UserSession(user: $user, sessionId: $sessionId, ip: '203.0.113.1', userAgent: 'Mozilla/5.0');
    }

    /**
     * killLiveSession() ne doit toucher QUE la table `sessions` + l'entité —
     * jamais rememberme_token, contrairement à forceLogout(). Un appareil avec
     * un cookie remember-me valide doit pouvoir rouvrir une session normalement.
     */
    public function testKillLiveSessionOnlyDeletesSessionRow(): void
    {
        $userSession = $this->createUserSession();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('DELETE FROM sessions'), ['id' => 'sess-123']);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($userSession);

        $revoker = new UserSessionRevoker($entityManager, $connection);
        $revoker->killLiveSession($userSession);
    }

    /**
     * forceLogout() doit tuer la session ET tous les jetons remember-me de
     * l'utilisateur : déconnexion complète, c'est le comportement attendu pour
     * une action de sécurité explicite (admin, ou self-service).
     */
    public function testForceLogoutDeletesSessionAndRememberMeTokens(): void
    {
        $userSession = $this->createUserSession(email: 'jane@example.com');

        $calls = [];
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params) use (&$calls) {
                $calls[] = [$sql, $params];

                return 1;
            });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($userSession);

        $revoker = new UserSessionRevoker($entityManager, $connection);
        $revoker->forceLogout($userSession);

        self::assertCount(2, $calls);
        self::assertStringContainsString('DELETE FROM sessions', $calls[0][0]);
        self::assertSame(['id' => 'sess-123'], $calls[0][1]);
        self::assertStringContainsString('DELETE FROM rememberme_token', $calls[1][0]);
        self::assertSame(['email' => 'jane@example.com'], $calls[1][1]);
    }

    public function testNeitherMethodFlushes(): void
    {
        // La responsabilité du flush revient à l'appelant (permet de grouper
        // plusieurs révocations dans une seule écriture, cf. revokeAll()).
        $userSession = $this->createUserSession();

        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(1);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $revoker = new UserSessionRevoker($entityManager, $connection);
        $revoker->forceLogout($userSession);
    }
}
