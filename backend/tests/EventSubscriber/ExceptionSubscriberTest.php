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
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class ExceptionSubscriberTest extends TestCase
{
    private function createEvent(\Throwable $throwable, ?Request $request = null): ExceptionEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new ExceptionEvent($kernel, $request ?? new Request(), HttpKernelInterface::MAIN_REQUEST, $throwable);
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

    /**
     * Régression : une AccessDeniedException "brute" (Security\Core, sans
     * HttpExceptionInterface) survient AVANT que le listener de sécurité du
     * firewall ne la convertisse en 403. Elle ne doit jamais être classée 5xx
     * ni déclencher d'alerte admin (sinon : fausse alerte "erreur 500" à chaque
     * appel anonyme d'une opération protégée, ex: GET /api/me sans token).
     */
    public function testRawAccessDeniedExceptionGoesToSecurityChannelAndDoesNotNotify(): void
    {
        $appErrors = $this->createStub(LoggerInterface::class);
        $securityErrors = $this->createMock(LoggerInterface::class);
        $securityErrors->expects($this->once())->method('log')->with('warning', $this->anything(), $this->anything());
        $businessErrors = $this->createStub(LoggerInterface::class);

        $emailManager = $this->createMock(EmailManager::class);
        $emailManager->expects($this->never())->method('sendNow');

        $subscriber = $this->createSubscriber($appErrors, $securityErrors, $businessErrors, $emailManager);
        $subscriber->onKernelException($this->createEvent(new AccessDeniedException("The user doesn't have ROLE_USER.")));
    }

    /**
     * Régression : idem pour une AuthenticationException brute (échec d'auth),
     * classée 401 et non notifiée.
     */
    public function testRawAuthenticationExceptionGoesToSecurityChannelAndDoesNotNotify(): void
    {
        $appErrors = $this->createStub(LoggerInterface::class);
        $securityErrors = $this->createMock(LoggerInterface::class);
        $securityErrors->expects($this->once())->method('log')->with('warning', $this->anything(), $this->anything());
        $businessErrors = $this->createStub(LoggerInterface::class);

        $emailManager = $this->createMock(EmailManager::class);
        $emailManager->expects($this->never())->method('sendNow');

        $subscriber = $this->createSubscriber($appErrors, $securityErrors, $businessErrors, $emailManager);
        $subscriber->onKernelException($this->createEvent(new AuthenticationException('Invalid credentials.')));
    }

    /**
     * Régression : le filtre fail2ban `symfony-security` (01-base-hardening.sh)
     * matche sur `"ip":"<HOST>"` dans le JSON journalisé -- sans ce champ dans
     * le contexte, la jail ne peut bannir personne même une fois le fichier de
     * log en place (cf. monolog.yaml, handler security_errors_file).
     */
    public function testAuthenticationExceptionLogsClientIpForFail2ban(): void
    {
        $appErrors = $this->createStub(LoggerInterface::class);
        $securityErrors = $this->createMock(LoggerInterface::class);
        $securityErrors->expects($this->once())
            ->method('log')
            ->with('warning', 'Invalid credentials.', $this->callback(
                static fn (array $context): bool => ($context['ip'] ?? null) === '203.0.113.42'
            ));
        $businessErrors = $this->createStub(LoggerInterface::class);
        $emailManager = $this->createStub(EmailManager::class);

        $request = Request::create('/api/login_check');
        $request->server->set('REMOTE_ADDR', '203.0.113.42');

        $subscriber = $this->createSubscriber($appErrors, $securityErrors, $businessErrors, $emailManager);
        $subscriber->onKernelException($this->createEvent(new AuthenticationException('Invalid credentials.'), $request));
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
