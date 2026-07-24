<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ExceptionSubscriber;
use App\Exception\ConflictException;
use App\Service\EmailManager;
use App\Service\ErrorNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class ExceptionSubscriberTest extends TestCase
{
    private function createEvent(\Throwable $throwable): ExceptionEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new ExceptionEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, $throwable);
    }

    /**
     * ErrorNotifier est "final" : on en construit une vraie instance avec un
     * EmailManager mocké (observable) plutôt que de mocker ErrorNotifier
     * lui-même (impossible pour une classe finale).
     */
    private function createSubscriber(LoggerInterface $appErrors, LoggerInterface $securityErrors, LoggerInterface $businessErrors, EmailManager $emailManager): ExceptionSubscriber
    {
        $notifier = new Notifier([]);
        $notifier->addAdminRecipient(new Recipient('admin@example.com'));

        $limiter = new RateLimiterFactory(
            ['id' => 'error_notification', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour'],
            new InMemoryStorage(),
        );

        $errorNotifier = new ErrorNotifier($emailManager, $notifier, $limiter, $this->createStub(LoggerInterface::class));

        return new ExceptionSubscriber($appErrors, $securityErrors, $businessErrors, $errorNotifier);
    }

    public function testBusinessExceptionGoesToBusinessErrorsChannelAndDoesNotNotify(): void
    {
        $appErrors = $this->createStub(LoggerInterface::class);
        $securityErrors = $this->createStub(LoggerInterface::class);
        $businessErrors = $this->createMock(LoggerInterface::class);
        $businessErrors->expects($this->once())->method('log')->with('warning', $this->anything(), $this->anything());

        $emailManager = $this->createMock(EmailManager::class);
        $emailManager->expects($this->never())->method('sendNow');

        $subscriber = $this->createSubscriber($appErrors, $securityErrors, $businessErrors, $emailManager);
        $subscriber->onKernelException($this->createEvent(new ConflictException('Un compte existe déjà avec cet email.')));
    }

    public function testAccessDeniedGoesToSecurityErrorsChannelAndDoesNotNotify(): void
    {
        $appErrors = $this->createStub(LoggerInterface::class);
        $securityErrors = $this->createMock(LoggerInterface::class);
        $securityErrors->expects($this->once())->method('log')->with('warning', $this->anything(), $this->anything());
        $businessErrors = $this->createStub(LoggerInterface::class);

        $emailManager = $this->createMock(EmailManager::class);
        $emailManager->expects($this->never())->method('sendNow');

        $subscriber = $this->createSubscriber($appErrors, $securityErrors, $businessErrors, $emailManager);
        $subscriber->onKernelException($this->createEvent(new AccessDeniedHttpException('Accès refusé.')));
    }

    public function testUnexpectedThrowableGoesToAppErrorsChannelAndNotifies(): void
    {
        $appErrors = $this->createMock(LoggerInterface::class);
        $appErrors->expects($this->once())->method('log')->with('error', $this->anything(), $this->anything());
        $securityErrors = $this->createStub(LoggerInterface::class);
        $businessErrors = $this->createStub(LoggerInterface::class);

        $emailManager = $this->createMock(EmailManager::class);
        $emailManager->expects($this->once())->method('sendNow')->willReturn(true);

        $subscriber = $this->createSubscriber($appErrors, $securityErrors, $businessErrors, $emailManager);
        $subscriber->onKernelException($this->createEvent(new \RuntimeException('Panne inattendue.')));
    }

    public function testAFailureInsideTheSubscriberNeverPropagates(): void
    {
        $appErrors = $this->createMock(LoggerInterface::class);
        $appErrors->method('log')->willThrowException(new \RuntimeException('logger down'));
        $appErrors->expects($this->once())->method('critical');

        $securityErrors = $this->createStub(LoggerInterface::class);
        $businessErrors = $this->createStub(LoggerInterface::class);
        $emailManager = $this->createStub(EmailManager::class);

        $subscriber = $this->createSubscriber($appErrors, $securityErrors, $businessErrors, $emailManager);

        // Ne doit lever aucune exception, malgré l'échec du logger.
        $subscriber->onKernelException($this->createEvent(new \RuntimeException('Panne inattendue.')));
        $this->addToAssertionCount(1);
    }
}
