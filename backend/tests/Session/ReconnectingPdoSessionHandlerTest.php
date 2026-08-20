<?php

namespace App\Tests\Session;

use App\Session\ReconnectingPdoSessionHandler;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

/**
 * Ces tests utilisent des doubles plutôt qu'une vraie base (comme
 * DoctrineConnectionPingSubscriberTest, le problème jumeau côté connexion
 * Doctrine) : seul pdo_mysql est installé dans cet environnement, pas
 * pdo_sqlite, et ce n'est de toute façon pas ce qu'il y a à prouver ici —
 * PdoSessionHandler lui-même est déjà testé par Symfony ; ce qui est à
 * nous, c'est l'orchestration délégation/retry autour de lui.
 */
final class ReconnectingPdoSessionHandlerTest extends TestCase
{
    private function goneAwayException(int $driverErrorCode = 2006): \PDOException
    {
        $message = \sprintf('SQLSTATE[HY000]: General error: %d MySQL server has gone away', $driverErrorCode);
        $exception = new \PDOException($message);
        $exception->errorInfo = ['HY000', $driverErrorCode, $message];

        return $exception;
    }

    /**
     * @return Connection&Stub
     */
    private function connectionStub(): Connection
    {
        // buildHandler() est appelé dans le constructeur : une chaîne sans
        // "://" (branche DSN brute de PdoSessionHandler::__construct) est
        // acceptée sans jamais se connecter avant un vrai accès — inoffensif
        // tant qu'on remplace $inner par un double juste après, comme dans
        // tous les tests ci-dessous. Un simple stub (pas de vérification
        // d'appel) suffit ici : le seul test qui a besoin de compter les
        // appels (retry) construit sa propre Connection&MockObject.
        $connection = $this->createStub(Connection::class);
        $connection->method('getNativeConnection')->willReturn('sqlite::memory:');

        return $connection;
    }

    private function replaceInner(ReconnectingPdoSessionHandler $handler, PdoSessionHandler $inner): void
    {
        $property = new \ReflectionProperty(ReconnectingPdoSessionHandler::class, 'inner');
        $property->setAccessible(true);
        $property->setValue($handler, $inner);
    }

    public function testDelegatesReadToTheInnerHandler(): void
    {
        $handler = new ReconnectingPdoSessionHandler($this->connectionStub(), $this->createStub(LoggerInterface::class));

        $inner = $this->createMock(PdoSessionHandler::class);
        $inner->expects($this->once())->method('read')->with('sess1')->willReturn('payload');
        $this->replaceInner($handler, $inner);

        $this->assertSame('payload', $handler->read('sess1'));
    }

    public function testDelegatesWriteToTheInnerHandler(): void
    {
        $handler = new ReconnectingPdoSessionHandler($this->connectionStub(), $this->createStub(LoggerInterface::class));

        $inner = $this->createMock(PdoSessionHandler::class);
        $inner->expects($this->once())->method('write')->with('sess1', 'payload')->willReturn(true);
        $this->replaceInner($handler, $inner);

        $this->assertTrue($handler->write('sess1', 'payload'));
    }

    public function testDelegatesDestroyToTheInnerHandler(): void
    {
        $handler = new ReconnectingPdoSessionHandler($this->connectionStub(), $this->createStub(LoggerInterface::class));

        $inner = $this->createMock(PdoSessionHandler::class);
        $inner->expects($this->once())->method('destroy')->with('sess1')->willReturn(true);
        $this->replaceInner($handler, $inner);

        $this->assertTrue($handler->destroy('sess1'));
    }

    public function testDelegatesGcToTheInnerHandler(): void
    {
        $handler = new ReconnectingPdoSessionHandler($this->connectionStub(), $this->createStub(LoggerInterface::class));

        $inner = $this->createMock(PdoSessionHandler::class);
        $inner->expects($this->once())->method('gc')->with(1800)->willReturn(3);
        $this->replaceInner($handler, $inner);

        $this->assertSame(3, $handler->gc(1800));
    }

    /**
     * Régression : le scénario exact constaté en prod sur dark.horlynat.com/login
     * (PdoSessionHandler.php:770) — DoctrineConnectionPingSubscriber protège
     * la connexion Doctrine, mais PdoSessionHandler travaillait jusqu'ici sur
     * un PDO figé (snapshot pris une fois via un factory Symfony, cf. le
     * docblock de ReconnectingPdoSessionHandler) qui ne suit jamais une
     * reconnexion Doctrine survenue après coup dans la vie d'un worker
     * FrankenPHP.
     *
     * getNativeConnection() est vérifié appelé exactement deux fois (une à
     * la construction, une après close()) : la preuve qu'un PDO natif frais
     * est bien redemandé plutôt que de rejouer sur le même objet mort.
     *
     * La retentative elle-même finit par échouer, mais avec une AUTRE
     * erreur que celle d'origine : "sqlite::memory:" n'est ici qu'une chaîne
     * factice (aucun driver pdo_sqlite dans cet environnement, seul
     * pdo_mysql l'est — cf. l'en-tête de ce fichier), donc le
     * PdoSessionHandler fraîchement reconstruit échoue à son tour, mais en
     * PHP \Error("...doit pas être accédée avant initialisation") plutôt
     * qu'en PDOException 2006. C'est justement la preuve recherchée : une
     * erreur DIFFÉRENTE de l'originale ne peut venir que d'une vraie
     * nouvelle tentative contre un handler neuf, pas d'un avalage silencieux
     * de l'erreur d'origine. En production, getNativeConnection() renvoie
     * toujours un vrai \PDO (jamais cette branche "chaîne"), donc ce cas
     * précis ne s'y produit pas.
     */
    public function testReconnectsAndRetriesOnceWhenTheSessionPdoHasGoneAway(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))->method('getNativeConnection')->willReturn('sqlite::memory:');
        $connection->expects($this->once())->method('close');

        $handler = new ReconnectingPdoSessionHandler($connection, $logger);

        $failingInner = $this->createMock(PdoSessionHandler::class);
        $failingInner->expects($this->once())->method('write')->willThrowException($this->goneAwayException());
        $this->replaceInner($handler, $failingInner);

        $this->expectException(\Error::class);
        $this->expectExceptionMessageMatches('/driver.*initializ/i');

        $handler->write('sess1', 'payload');
    }

    /**
     * Fixe $openPath/$openName par réflexion — même technique que
     * replaceInner() ci-dessus pour $inner — plutôt que d'appeler le vrai
     * open() : celui-ci connecte immédiatement (PdoSessionHandler::open(),
     * si $pdo n'est pas déjà initialisé), ce qui échouerait ici avec le faux
     * DSN "sqlite::memory:" avant même d'atteindre le scénario à tester.
     */
    private function setOpenState(ReconnectingPdoSessionHandler $handler, string $path, string $name): void
    {
        foreach (['openPath' => $path, 'openName' => $name] as $property => $value) {
            $reflection = new \ReflectionProperty(ReconnectingPdoSessionHandler::class, $property);
            $reflection->setAccessible(true);
            $reflection->setValue($handler, $value);
        }
    }

    /**
     * Régression du 20/08/2026 : le test ci-dessus ne couvre qu'une seule
     * opération isolée. En vrai, Symfony appelle TOUJOURS open() en premier à
     * chaque requête — et c'est justement ce open() initial qui n'était
     * jamais rejoué sur le nouveau handler interne quand la reconstruction
     * était déclenchée par une opération ULTÉRIEURE (read(), ici, comme
     * constaté en prod sur /login) : le handler neuf refusait alors toute
     * opération avec LogicException "Session name cannot be empty"
     * (AbstractSessionHandler) au lieu de rejouer read().
     *
     * Preuve recherchée : le handler reconstruit tente un vrai open() — donc
     * une vraie (re)connexion, ici avec le faux DSN "sqlite::memory:", qui
     * échoue en PDOException "could not find driver" — avant que read() ne
     * soit rejoué. Cette erreur, différente à la fois de la PDOException
     * d'origine (2006) et de la LogicException du bug, ne peut venir que d'un
     * open() effectivement rejoué sur le nouveau handler.
     */
    public function testReplaysOpenOnTheRebuiltHandlerBeforeRetryingALaterOperation(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))->method('getNativeConnection')->willReturn('sqlite::memory:');
        $connection->expects($this->once())->method('close');

        $handler = new ReconnectingPdoSessionHandler($connection, $this->createStub(LoggerInterface::class));
        $this->setOpenState($handler, '/tmp/sessions', 'PHPSESSID');

        $failingInner = $this->createMock(PdoSessionHandler::class);
        $failingInner->expects($this->once())->method('read')->willThrowException($this->goneAwayException());
        $this->replaceInner($handler, $failingInner);

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('could not find driver');

        $handler->read('sess1');
    }

    public function testDoesNotRetryAndRethrowsWhenTheErrorIsNotAConnectionLoss(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getNativeConnection')->willReturn('sqlite::memory:');
        $connection->expects($this->never())->method('close');

        $handler = new ReconnectingPdoSessionHandler($connection, $this->createStub(LoggerInterface::class));

        $otherError = new \PDOException('SQLSTATE[42S02]: Base table or view not found');
        $otherError->errorInfo = ['42S02', 1146, 'Table does not exist'];

        $failingInner = $this->createStub(PdoSessionHandler::class);
        $failingInner->method('write')->willThrowException($otherError);
        $this->replaceInner($handler, $failingInner);

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('Base table or view not found');

        $handler->write('sess1', 'payload');
    }

    #[DataProvider('provideLostConnectionCodes')]
    public function testClassifiesKnownConnectionLossCodes(int $code, bool $expectedLost): void
    {
        $method = new \ReflectionMethod(ReconnectingPdoSessionHandler::class, 'isConnectionLost');
        $method->setAccessible(true);

        $this->assertSame($expectedLost, $method->invoke(null, $this->goneAwayException($code)));
    }

    /** @return iterable<string, array{int, bool}> */
    public static function provideLostConnectionCodes(): iterable
    {
        // Mêmes codes que Doctrine\DBAL\Driver\API\MySQL\ExceptionConverter
        // pour ConnectionLost — vérifié dans le vendor avant d'écrire le fix.
        yield 'gone away (2006)' => [2006, true];
        yield 'disconnected for inactivity (4031)' => [4031, true];
        // 2013 (lost connection DURING query) n'est PAS mappé sur
        // ConnectionLost côté Doctrine (reste un DriverException générique) —
        // exclu ici pour rester cohérent avec ce que Doctrine considère sûr
        // à traiter comme "la connexion est morte", pas juste cette requête.
        yield 'lost during query (2013, non mappé côté Doctrine)' => [2013, false];
        yield 'access denied (1045)' => [1045, false];
    }
}
