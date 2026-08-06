<?php

namespace App\Tests\EventListener;

use App\EventListener\TwoFactorAttemptListener;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class TwoFactorAttemptListenerTest extends TestCase
{
    private function createListener(RateLimiterFactory $factory): TwoFactorAttemptListener
    {
        return new TwoFactorAttemptListener($factory);
    }

    private function createLimiter(): RateLimiterFactory
    {
        return new RateLimiterFactory(
            ['id' => 'two_factor_attempt', 'policy' => 'fixed_window', 'limit' => 5, 'interval' => '15 minutes'],
            new InMemoryStorage(),
        );
    }

    private function createEvent(): TwoFactorAuthenticationEvent
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(new InMemoryUser('alice@example.com', null));

        return new TwoFactorAuthenticationEvent(new Request(), $token);
    }

    public function testBlocksAfterTooManyAttempts(): void
    {
        $listener = $this->createListener($this->createLimiter());
        $event = $this->createEvent();

        // 5 tentatives passent (limite du limiter).
        for ($i = 0; $i < 5; ++$i) {
            $listener->onAttempt($event);
        }

        // La 6e doit être bloquée.
        $this->expectException(TooManyLoginAttemptsAuthenticationException::class);
        $listener->onAttempt($event);
    }

    public function testSuccessResetsTheCounter(): void
    {
        $listener = $this->createListener($this->createLimiter());
        $event = $this->createEvent();

        for ($i = 0; $i < 5; ++$i) {
            $listener->onAttempt($event);
        }

        // Un succès réinitialise le compteur : une nouvelle tentative repasse.
        $listener->onSuccess($event);
        $listener->onAttempt($event);

        $this->addToAssertionCount(1);
    }
}
