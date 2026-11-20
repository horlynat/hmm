<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\UserSession;
use App\EventSubscriber\SessionTokenVersionSubscriber;
use App\Repository\UserSessionRepository;
use App\Service\UserSessionRevoker;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class UserSessionRevokerTest extends TestCase
{
    private function createUserSession(string $email = 'jane@example.com', string $sessionId = 'sess-123'): UserSession
    {
        $user = new User();
        $user->setEmail($email);

        return new UserSession(user: $user, sessionId: $sessionId, ip: '203.0.113.1', userAgent: 'Mozilla/5.0');
    }

    /**
     * @param list<UserSession> $otherSessions
     */
    private function revoker(
        EntityManagerInterface $entityManager,
        Connection $connection,
        array $otherSessions = [],
        ?Request $currentRequest = null,
    ): UserSessionRevoker {
        $repository = $this->createStub(UserSessionRepository::class);
        $repository->method('findBy')->willReturn($otherSessions);

        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn($currentRequest);

        return new UserSessionRevoker($entityManager, $connection, $repository, $requestStack);
    }

    /**
     * killLiveSession() ne doit toucher QUE la table `sessions` + l'entité —
     * jamais rememberme_token/token_version, contrairement à forceLogout()/
     * revokeAll(). Un appareil avec un cookie remember-me valide doit pouvoir
     * rouvrir une session normalement.
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

        $this->revoker($entityManager, $connection)->killLiveSession($userSession);
    }

    /**
     * forceLogout() doit tuer la session, tous les jetons remember-me ET
     * incrémenter token_version (P1 #8) : déconnexion complète, y compris
     * les JWT émis pour ce compte.
     */
    public function testForceLogoutDeletesSessionRememberMeAndBumpsTokenVersion(): void
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

        $revoker = $this->revoker($entityManager, $connection);
        $initialVersion = $userSession->getUser()->getTokenVersion();
        $revoker->forceLogout($userSession);

        self::assertCount(2, $calls);
        self::assertStringContainsString('DELETE FROM sessions', $calls[0][0]);
        self::assertSame(['id' => 'sess-123'], $calls[0][1]);
        self::assertStringContainsString('DELETE FROM rememberme_token', $calls[1][0]);
        self::assertSame(['email' => 'jane@example.com'], $calls[1][1]);
        self::assertSame($initialVersion + 1, $userSession->getUser()->getTokenVersion());
    }

    public function testNeitherKillLiveSessionNorRevokeAllFlushes(): void
    {
        // La responsabilité du flush revient à l'appelant (permet de grouper
        // plusieurs révocations dans une seule écriture).
        $userSession = $this->createUserSession();

        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(1);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $this->revoker($entityManager, $connection)->forceLogout($userSession);
    }

    /**
     * revokeAll() détruit toutes les sessions web connues du compte — y
     * compris celles qu'aucun appel à killLiveSession() direct n'a visées
     * (ex: appelé depuis une action admin, sans UserSession "déclencheuse").
     */
    public function testRevokeAllKillsEveryKnownSession(): void
    {
        $user = new User();
        $user->setEmail('jane@example.com');
        $s1 = new UserSession(user: $user, sessionId: 'sess-1');
        $s2 = new UserSession(user: $user, sessionId: 'sess-2');

        $removed = [];
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('remove')->willReturnCallback(function (UserSession $s) use (&$removed): void {
            $removed[] = $s;
        });

        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(1);

        $revoker = $this->revoker($entityManager, $connection, otherSessions: [$s1, $s2]);
        $killed = $revoker->revokeAll($user);

        self::assertSame(2, $killed);
        self::assertSame([$s1, $s2], $removed);
    }

    /**
     * La session web DE CE MÊME compte qui a déclenché la révocation (ex:
     * l'utilisateur change son propre mot de passe) est conservée — toutes
     * les autres tombent. Son marqueur de version est resynchronisé, sinon
     * SessionTokenVersionSubscriber la détruirait à la requête suivante.
     */
    public function testRevokeAllKeepsTheCallersOwnSessionAndResyncsItsMarker(): void
    {
        $user = new User();
        $user->setEmail('jane@example.com');
        $other = new UserSession(user: $user, sessionId: 'sess-other');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($other);

        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(1);

        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set(SessionTokenVersionSubscriber::SESSION_USER_KEY, 'jane@example.com');

        $request = $this->createStub(Request::class);
        $request->method('hasSession')->willReturn(true);
        $request->method('getPathInfo')->willReturn('/user/profil/modifier');
        $request->method('getSession')->willReturn($session);

        $revoker = $this->revoker($entityManager, $connection, otherSessions: [$other], currentRequest: $request);
        $revoker->revokeAll($user);

        self::assertSame($user->getTokenVersion(), $session->get(SessionTokenVersionSubscriber::SESSION_KEY));
    }

    /**
     * Sur le firewall API (stateless), la "session courante" ne doit jamais
     * être consultée (pas de session sur ce firewall) : toutes les sessions
     * web connues tombent sans exception, même celle de l'appelant.
     */
    public function testRevokeAllIgnoresSessionOnApiRequests(): void
    {
        $user = new User();
        $user->setEmail('jane@example.com');
        $webSession = new UserSession(user: $user, sessionId: 'sess-web');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($webSession);

        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(1);

        $request = $this->createStub(Request::class);
        $request->method('hasSession')->willReturn(true);
        $request->method('getPathInfo')->willReturn('/api/me');

        $revoker = $this->revoker($entityManager, $connection, otherSessions: [$webSession], currentRequest: $request);
        $killed = $revoker->revokeAll($user);

        self::assertSame(1, $killed);
    }
}
