<?php

namespace App\EventSubscriber;

use App\Security\SuperAdminElevationContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Positionne (ou efface) le contexte d'élévation PAM au tout début de chaque
 * requête principale — priorité 9 : APRÈS l'ouverture de session (128), AVANT
 * les firewalls (8), pour que le rechargement de l'utilisateur par le
 * ContextListener voie déjà les bons rôles. Cf. SuperAdminElevationContext.
 */
final class SuperAdminElevationSubscriber implements EventSubscriberInterface, ResetInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9],
            // Effacé dès la fin de la requête : rien de ce qui suit (CLI dans
            // le même processus, tâche post-réponse…) n'en hérite.
            KernelEvents::TERMINATE => ['reset', -1024],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        SuperAdminElevationContext::bindFromRequest($event->getRequest());
    }

    public function reset(): void
    {
        SuperAdminElevationContext::reset();
    }
}
