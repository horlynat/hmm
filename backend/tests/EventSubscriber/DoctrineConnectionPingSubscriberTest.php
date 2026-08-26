<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\DoctrineConnectionPingSubscriber;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PDODriverException;
use Doctrine\DBAL\Exception\ConnectionLost;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Worker;

final class DoctrineConnectionPingSubscriberTest extends TestCase
{
    private function createEvent(bool $isMainRequest = true): RequestEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $requestType = $isMainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST;

        return new RequestEvent($kernel, new Request(), $requestType);
    }

    private function createWorkerRunningEvent(bool $isWorkerIdle = false): WorkerRunningEvent
    {
        return new WorkerRunningEvent($this->createStub(Worker::class), $isWorkerIdle);
    }

    public function testDoesNothingWhenConnectionNotYetOpened(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isConnected')->willReturn(false);
        $connection->expects($this->never())->method('fetchOne');
        $connection->expects($this->never())->method('close');

        $subscriber = new DoctrineConnectionPingSubscriber($connection, $this->createStub(LoggerInterface::class));
        $subscriber->onKernelRequest($this->createEvent());
    }

    public function testDoesNothingOnSubRequestEvenIfConnected(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isConnected')->willReturn(true);
        $connection->expects($this->never())->method('fetchOne');

        $subscriber = new DoctrineConnectionPingSubscriber($connection, $this->createStub(LoggerInterface::class));
        $subscriber->onKernelRequest($this->createEvent(isMainRequest: false));
    }

    public function testPingsAliveConnectionAndDoesNotCloseIt(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isConnected')->willReturn(true);
        $connection->expects($this->once())->method('fetchOne')->with('SELECT 1')->willReturn(1);
        $connection->expects($this->never())->method('close');

        $subscriber = new DoctrineConnectionPingSubscriber($connection, $this->createStub(LoggerInterface::class));
        $subscriber->onKernelRequest($this->createEvent());
    }

    /**
     * Régression : c'est le scénario exact de l'incident — worker FrankenPHP
     * dont la connexion a été coupée par le wait_timeout MySQL pendant un
     * creux de trafic (erreur 4031, mappée par Doctrine sur ConnectionLost).
     */
    public function testClosesDeadConnectionSoDoctrineReopensOneOnNextUse(): void
    {
        $connectionLost = new ConnectionLost(
            PDODriverException::new(new \PDOException('SQLSTATE[HY000]: General error: 4031 The client was disconnected by the server because of inactivity.', 4031)),
            null,
        );

        $connection = $this->createMock(Connection::class);
        $connection->method('isConnected')->willReturn(true);
        $connection->method('fetchOne')->willThrowException($connectionLost);
        $connection->expects($this->once())->method('close');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $subscriber = new DoctrineConnectionPingSubscriber($connection, $logger);
        $subscriber->onKernelRequest($this->createEvent());
    }

    /**
     * messenger-worker (docker-compose.prod.yml) consomme la file async
     * jusqu'à 1h d'affilée avec sa propre connexion Doctrine, sans jamais
     * passer par kernel.request — même angle mort que l'incident
     * dark.horlynat.com/login, mais côté worker Messenger plutôt que HTTP.
     */
    public function testWorkerRunningDoesNothingWhenConnectionNotYetOpened(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isConnected')->willReturn(false);
        $connection->expects($this->never())->method('fetchOne');
        $connection->expects($this->never())->method('close');

        $subscriber = new DoctrineConnectionPingSubscriber($connection, $this->createStub(LoggerInterface::class));
        $subscriber->onWorkerRunning($this->createWorkerRunningEvent());
    }

    public function testWorkerRunningPingsAliveConnectionAndDoesNotCloseIt(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isConnected')->willReturn(true);
        $connection->expects($this->once())->method('fetchOne')->with('SELECT 1')->willReturn(1);
        $connection->expects($this->never())->method('close');

        $subscriber = new DoctrineConnectionPingSubscriber($connection, $this->createStub(LoggerInterface::class));
        // isWorkerIdle: true — un tick sans message doit sonder comme n'importe quel autre.
        $subscriber->onWorkerRunning($this->createWorkerRunningEvent(isWorkerIdle: true));
    }

    /**
     * Régression : le pendant messenger-worker de
     * testClosesDeadConnectionSoDoctrineReopensOneOnNextUse ci-dessus — la
     * boucle Worker::run() dispatche WorkerRunningEvent après CHAQUE
     * itération (message traité ou non), donc la connexion se soigne avant
     * la prochaine plutôt que de rester cassée jusqu'au recyclage du worker
     * (jusqu'à 1h, cf. --time-limit=3600).
     */
    public function testWorkerRunningClosesDeadConnectionSoDoctrineReopensOneOnNextUse(): void
    {
        $connectionLost = new ConnectionLost(
            PDODriverException::new(new \PDOException('SQLSTATE[HY000]: General error: 4031 The client was disconnected by the server because of inactivity.', 4031)),
            null,
        );

        $connection = $this->createMock(Connection::class);
        $connection->method('isConnected')->willReturn(true);
        $connection->method('fetchOne')->willThrowException($connectionLost);
        $connection->expects($this->once())->method('close');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $subscriber = new DoctrineConnectionPingSubscriber($connection, $logger);
        $subscriber->onWorkerRunning($this->createWorkerRunningEvent());
    }
}
