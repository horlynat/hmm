<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Invalide toute session web émise avant le dernier incrément de
 * User::$tokenVersion (changement de mot de passe, déconnexion forcée… cf.
 * UserSessionRevoker::revokeAll()).
 *
 * Nécessaire en plus de la suppression des lignes `sessions` : une session
 * restaurée par remember-me (authentification non interactive) n'a pas
 * d'entité UserSession, elle échappait donc à toute révocation.
 *
 * - À la connexion (LoginSuccessEvent, firewall main) : la version courante
 *   est mémorisée en session.
 * - À chaque requête (après les firewalls, priorité 7) : version mémorisée ≠
 *   version en base → session détruite, redirection vers /login.
 * - Session antérieure à ce mécanisme (aucune version mémorisée) :
 *   initialisée paresseusement.
 */
final class SessionTokenVersionSubscriber implements EventSubscriberInterface
{
    public const SESSION_KEY = '_auth_token_version';
    public const SESSION_USER_KEY = '_auth_token_version_user';

    public function __construct(
        private readonly Security $security,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 7],
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $event->getRequest();
        if (!$user instanceof User || 'main' !== $event->getFirewallName() || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $session->set(self::SESSION_KEY, $user->getTokenVersion());
        $session->set(self::SESSION_USER_KEY, $user->getUserIdentifier());
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (str_starts_with($request->getPathInfo(), '/api') || !$request->hasPreviousSession()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $session = $request->getSession();
        $stored = $session->get(self::SESSION_KEY);
        $storedUser = $session->get(self::SESSION_USER_KEY);

        if (!\is_int($stored) || $storedUser !== $user->getUserIdentifier()) {
            $session->set(self::SESSION_KEY, $user->getTokenVersion());
            $session->set(self::SESSION_USER_KEY, $user->getUserIdentifier());

            return;
        }

        if ($stored === $user->getTokenVersion()) {
            return;
        }

        $this->tokenStorage->setToken(null);
        $session->invalidate();
        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('login')));
    }
}
