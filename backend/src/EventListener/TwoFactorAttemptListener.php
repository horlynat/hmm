<?php

namespace App\EventListener;

use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Limite les tentatives de saisie du code TOTP à la connexion (/2fa_check).
 *
 * Scheb 2FA ne throttle pas nativement : sans ce garde-fou, un attaquant qui a
 * déjà le mot de passe (première étape) pourrait bruteforcer le second facteur
 * à 6 chiffres. On consomme un jeton du limiter "two_factor_attempt" à chaque
 * tentative (event ATTEMPT, avant la vérification du code) et on réinitialise
 * le compteur en cas de succès.
 */
final class TwoFactorAttemptListener
{
    public function __construct(
        #[Autowire(service: 'limiter.two_factor_attempt')] private readonly RateLimiterFactory $twoFactorAttemptLimiter,
    ) {
    }

    #[AsEventListener(event: TwoFactorAuthenticationEvents::ATTEMPT)]
    public function onAttempt(TwoFactorAuthenticationEvent $event): void
    {
        $limiter = $this->twoFactorAttemptLimiter->create($this->resolveKey($event));

        if (!$limiter->consume(1)->isAccepted()) {
            throw new TooManyLoginAttemptsAuthenticationException();
        }
    }

    #[AsEventListener(event: TwoFactorAuthenticationEvents::SUCCESS)]
    public function onSuccess(TwoFactorAuthenticationEvent $event): void
    {
        $this->twoFactorAttemptLimiter->create($this->resolveKey($event))->reset();
    }

    private function resolveKey(TwoFactorAuthenticationEvent $event): string
    {
        $user = $event->getToken()->getUser();

        return $user instanceof UserInterface ? '2fa_'.$user->getUserIdentifier() : '2fa_unknown';
    }
}
