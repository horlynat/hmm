<?php

namespace App\Tests\Service;

use App\Service\EmailManager;
use App\Service\ErrorNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class ErrorNotifierTest extends TestCase
{
    /**
     * Notifier est une classe "final" : on en construit une vraie instance
     * plutôt que de la mocker (PHP ne permet pas de sous-classer une classe
     * finale, y compris via un test double).
     */
    private function createNotifierWithAdminRecipient(): Notifier
    {
        $notifier = new Notifier([]);
        $notifier->addAdminRecipient(new Recipient('admin@example.com'));

        return $notifier;
    }

    private function createLimiter(): RateLimiterFactory
    {
        return new RateLimiterFactory(
            ['id' => 'error_notification', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour'],
            new InMemoryStorage(),
        );
    }

    public function testSendsExactlyOneEmailPerExceptionClassWithinTheWindow(): void
    {
        $emailManager = $this->createMock(EmailManager::class);
        $emailManager->expects($this->once())->method('sendNow')->willReturn(true);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $errorNotifier = new ErrorNotifier(
            $emailManager,
            $this->createNotifierWithAdminRecipient(),
            $this->createLimiter(),
            $logger,
        );

        $exception = new \RuntimeException('boom');

        // Deux erreurs de la même classe dans la fenêtre : un seul email.
        $errorNotifier->notify($exception, 500);
        $errorNotifier->notify($exception, 500);
    }

    public function testDifferentExceptionClassesAreNotifiedIndependently(): void
    {
        $emailManager = $this->createMock(EmailManager::class);
        $emailManager->expects($this->exactly(2))->method('sendNow')->willReturn(true);

        $errorNotifier = new ErrorNotifier(
            $emailManager,
            $this->createNotifierWithAdminRecipient(),
            $this->createLimiter(),
            $this->createStub(LoggerInterface::class),
        );

        $errorNotifier->notify(new \RuntimeException('boom'), 500);
        $errorNotifier->notify(new \LogicException('autre erreur'), 500);
    }

    public function testAFailureToSendNeverPropagates(): void
    {
        $emailManager = $this->createStub(EmailManager::class);
        $emailManager->method('sendNow')->willThrowException(new \RuntimeException('SMTP indisponible'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $errorNotifier = new ErrorNotifier(
            $emailManager,
            $this->createNotifierWithAdminRecipient(),
            $this->createLimiter(),
            $logger,
        );

        // Ne doit lever aucune exception, malgré l'échec de sendNow().
        $errorNotifier->notify(new \RuntimeException('boom'), 500);
        $this->addToAssertionCount(1);
    }
}
