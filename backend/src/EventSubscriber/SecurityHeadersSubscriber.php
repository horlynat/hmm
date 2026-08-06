<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ajoute les en-têtes de sécurité que NelmioSecurityBundle ne gère pas
 * nativement, en défense en profondeur. Ce qui est déjà couvert par
 * nelmio_security.yaml (X-Frame-Options, X-Content-Type-Options, CSP, HSTS,
 * Referrer-Policy) n'est PAS redéfini ici pour éviter les doublons.
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Priorité négative : passe après les listeners de nelmio pour ne pas
        // être écrasé, et n'ajoute que des en-têtes absents.
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -256],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;

        // Neutralise par défaut les API navigateur sensibles dont l'app n'a pas
        // besoin (géoloc, caméra, micro, USB, paiement...) : limite l'impact
        // d'un éventuel script injecté.
        $headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=(), usb=(), payment=(), magnetometer=(), gyroscope=(), accelerometer=(), interest-cohort=()');

        // Isole le contexte de navigation (protège contre les attaques
        // cross-window de type XS-Leaks / tabnabbing).
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Empêche Adobe Flash/Acrobat de charger des données cross-domain
        // depuis ce domaine via un crossdomain.xml.
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');
    }
}
