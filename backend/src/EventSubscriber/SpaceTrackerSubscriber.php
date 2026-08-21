<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Mémorise en session le dernier "espace" Symfony visité (admin ou membre),
 * à partir du préfixe du nom de route (admin_* / member_*).
 *
 * Sert aux pages partagées entre les deux espaces (TwoFactorController,
 * seule aujourd'hui) : sans ça, un compte back-office qui navigue
 * ponctuellement dans l'espace membre (ex. /projects, pour tester le
 * parcours collaborateur avec son propre compte admin) se voyait renvoyer
 * l'aside admin dès qu'il cliquait sur "Sécurité & 2FA" depuis le menu
 * membre — cohérent avec AccountLinkResolver::isBackOfficeUser() (le rôle
 * prime), mais déroutant : on quitte l'espace dans lequel on vient de
 * cliquer. AccountLinkResolver reste la source de vérité pour les emails et
 * pour le tout premier accès d'une session (aucun espace encore mémorisé) —
 * cette classe n'ajoute qu'un raffinement contextuel par-dessus, jamais un
 * changement de droits.
 */
final class SpaceTrackerSubscriber implements EventSubscriberInterface
{
    public const SESSION_KEY = 'last_visited_space';
    public const SPACE_ADMIN = 'admin';
    public const SPACE_MEMBER = 'member';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }

        $route = (string) $request->attributes->get('_route');

        $space = match (true) {
            str_starts_with($route, 'admin_') => self::SPACE_ADMIN,
            str_starts_with($route, 'member_') => self::SPACE_MEMBER,
            default => null,
        };

        if (null === $space) {
            // Route neutre (accueil, 2FA, profil commun...) : on ne change
            // rien, l'espace mémorisé reste celui du dernier écran propre à
            // l'un des deux espaces.
            return;
        }

        $request->getSession()->set(self::SESSION_KEY, $space);
    }
}
