<?php

namespace App\EventListener;

use App\Repository\UserRepository;
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
 *
 * Contre-audit B2 (M1) : les compteurs portent sur l'adresse CANONIQUE du
 * compte. La colonne user.email est en utf8mb4_0900_ai_ci (insensible aux
 * accents) : `vïctim@…` et `vîctim@…` chargent le même compte, mais
 * avaient chacun leur compteur — le délai se contournait en changeant
 * d'accent. On résout donc l'adresse par la base (même collation que la
 * connexion) ; pour une adresse inconnue, repli sur un pliage ASCII
 * (mêmes variantes regroupées, aucune différence de comportement qui
 * trahirait l'existence du compte).
 */
final class LoginThrottleListener
{
    private const PASSWORD_FIREWALLS = ['main', 'api_login'];

    public function __construct(
        private readonly LoginThrottle $throttle,
        private readonly LoggerInterface $logger,
        private readonly RequestStack $requestStack,
        private readonly UserRepository $userRepository,
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
        $wait = $this->throttle->retryAfter($this->canonical($badge->getUserIdentifier()), $this->requestStack->getMainRequest()?->getClientIp());
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
            $this->throttle->recordFailure($this->canonical($email), $event->getRequest()->getClientIp());
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

        $this->throttle->recordSuccess($this->canonical($event->getUser()->getUserIdentifier()), $event->getRequest()->getClientIp());
    }

    private function canonical(string $identifier): string
    {
        $identifier = trim($identifier);
        if ('' !== $identifier && mb_strlen($identifier) <= 180) {
            try {
                $user = $this->userRepository->findOneBy(['email' => $identifier]);
                if (null !== $user) {
                    return $user->getUserIdentifier();
                }
            } catch (\Throwable $e) {
                $this->logger->warning('LoginThrottle : résolution de l\'adresse canonique impossible.', ['exception' => $e]);
            }
        }

        static $fold = null;
        $fold ??= \Transliterator::create('Any-Latin; Latin-ASCII; Lower()') ?: false;

        return false === $fold ? $identifier : (string) ($fold->transliterate($identifier) ?: $identifier);
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
