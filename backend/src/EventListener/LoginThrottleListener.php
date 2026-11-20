<?php

namespace App\EventListener;

use App\Security\LoginThrottle;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Branche LoginThrottle sur toute authentification par mot de passe
 * (formulaire web du firewall `main` ET json_login de /api/login_check).
 *
 * - CheckPassportEvent, priorité 512 : AVANT le chargement de l'utilisateur
 *   (UserChecker::checkPreAuth, 256) et la vérification du mot de passe (0).
 *   Pendant un délai, la réponse est donc strictement la même que le mot de
 *   passe soit bon ou mauvais, et que le compte existe ou non.
 * - LoginFailureEvent, priorité 100 : compte l'échec AVANT le journal
 *   (LoginListener), dont une erreur d'écriture ne peut donc plus empêcher
 *   le délai. Seuls les « mauvais identifiants » comptent : ni un refus pour
 *   délai (pas de réarmement), ni un CSRF invalide, ni un statut de compte.
 * - LoginSuccessEvent : remet la paire (e-mail, IP) à zéro et mémorise l'IP
 *   comme connue pour ce compte.
 */
final class LoginThrottleListener
{
    private const PASSWORD_FIREWALLS = ['main', 'api_login'];

    public function __construct(
        private readonly LoginThrottle $throttle,
        private readonly LoggerInterface $logger,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[AsEventListener(event: CheckPassportEvent::class, priority: 512)]
    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(PasswordCredentials::class) || !$passport->hasBadge(UserBadge::class)) {
            return;
        }

        /** @var UserBadge $badge */
        $badge = $passport->getBadge(UserBadge::class);
        $wait = $this->throttle->retryAfter($badge->getUserIdentifier(), $this->requestStack->getMainRequest()?->getClientIp());
        if ($wait > 0) {
            throw new TooManyLoginAttemptsAuthenticationException((int) ceil($wait / 60));
        }
    }

    #[AsEventListener(event: LoginFailureEvent::class, priority: 100)]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        if (!\in_array($event->getFirewallName(), self::PASSWORD_FIREWALLS, true)) {
            return;
        }

        $exception = $event->getException();
        if (!$exception instanceof BadCredentialsException) {
            return;
        }

        $email = $this->identifierFrom($event);
        if (null === $email) {
            return;
        }

        try {
            $this->throttle->recordFailure($email, $event->getRequest()->getClientIp());
        } catch (\Throwable $e) {
            $this->logger->error('LoginThrottle : impossible d\'enregistrer un échec de connexion.', ['exception' => $e]);
        }
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if (!\in_array($event->getFirewallName(), self::PASSWORD_FIREWALLS, true)
            || !$event->getPassport()->hasBadge(PasswordCredentials::class)) {
            return;
        }

        $this->throttle->recordSuccess($event->getUser()->getUserIdentifier(), $event->getRequest()->getClientIp());
    }

    private function identifierFrom(LoginFailureEvent $event): ?string
    {
        $passport = $event->getPassport();
        if (null !== $passport && $passport->hasBadge(UserBadge::class)) {
            /** @var UserBadge $badge */
            $badge = $passport->getBadge(UserBadge::class);

            return $badge->getUserIdentifier();
        }

        $email = trim((string) $event->getRequest()->getPayload()->get('email'));

        return '' === $email ? null : $email;
    }
}
