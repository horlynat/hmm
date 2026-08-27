<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\LoginFailureLoggerSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Régression : AuthenticatorManager gère un échec de connexion en interne
 * (canal Monolog natif `security`, jamais ExceptionSubscriber) — sans ce
 * listener, fail2ban ne voit jamais passer les tentatives échouées sur le
 * formulaire web, quel que soit l'état du handler security_errors_file
 * (cf. monolog.yaml).
 */
final class LoginFailureLoggerSubscriberTest extends TestCase
{
    public function testLogsFixedMessageWithClientIpForFail2ban(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Invalid credentials.', $this->callback(
                static fn (array $context): bool => '203.0.113.42' === ($context['ip'] ?? null)
                    && 'main' === ($context['firewall'] ?? null)
            ));

        $subscriber = new LoginFailureLoggerSubscriber($logger);

        $request = Request::create('/login');
        $request->server->set('REMOTE_ADDR', '203.0.113.42');

        $event = new LoginFailureEvent(
            new BadCredentialsException('Invalid credentials.'),
            $this->createStub(AuthenticatorInterface::class),
            $request,
            null,
            'main',
        );

        $subscriber->onLoginFailure($event);
    }

    public function testSubscribesToLoginFailureEvent(): void
    {
        self::assertSame(
            [LoginFailureEvent::class => 'onLoginFailure'],
            LoginFailureLoggerSubscriber::getSubscribedEvents(),
        );
    }
}
