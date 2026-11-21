<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Security\TwoFactorMandatePolicy;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * P1 #13 (b) — pour un compte soumis à la 2FA obligatoire
 * (App\Security\TwoFactorMandatePolicy) qui ne l'a pas activée, bloque
 * TOUTE requête `/api/**` authentifiée avec 403 JSON
 * `{"code":"2fa_required"}` — CONTRAT partagé avec le lot frontend.
 *
 * Contre-audit B2 : limité à `/api/me/**`, le contrôle laissait un
 * ROLE_ADMIN sans TOTP lire, avec son seul mot de passe, les ressources
 * API Platform réservées au staff (devis, messages de contact, abonnés
 * newsletter, tickets…). Le périmètre est donc tout `/api`, sauf la
 * liste blanche ci-dessous ; une requête anonyme n'est jamais concernée.
 *
 * Avant ce correctif, `/api/login_check` délivrait un jeton complet même
 * pour un compte 2FA obligatoire non équipé (aucun second facteur
 * n'existait côté API avant TwoFactorAwareJwtSuccessHandler, et même
 * désormais, un compte sans TOTP activé n'a tout simplement jamais de
 * challenge à passer) : tout `/api/me/*` restait utilisable sans jamais
 * avoir mis en place la 2FA.
 *
 * Exemptions (CONTRAT) :
 * - `GET /api/me` (le frontend doit pouvoir lire `isTwoFactorEnabled` pour
 *   savoir s'il doit rediriger vers la mise en place) ;
 * - `/api/me/2fa/**` (le flux de mise en place lui-même) ;
 * - `POST /api/logout` (se déconnecter doit toujours rester possible) ;
 * - `/api/login_check/**` (connexion et second facteur : firewall dédié,
 *   aucun utilisateur résolu ici de toute façon).
 *
 * `kernel.request`, après le firewall (qui résout déjà l'utilisateur pour un
 * firewall stateless comme `api`).
 *
 * Contre-audit (2) : `getPathInfo()` garde l'URL encodée (`%2F`, `%61`…),
 * alors que le pare-feu (PathRequestMatcher) et le routeur (UrlMatcher)
 * comparent tous deux `rawurldecode($pathinfo)` — `GET /%61pi/me/activity`
 * passait donc le pare-feu et le routeur sans jamais matcher `#^/api#` ici.
 * Même décodage unique qu'eux, pour rester sur le chemin réellement servi.
 */
final class ApiTwoFactorEnforcementSubscriber implements EventSubscriberInterface
{
    private const IN_SCOPE_PATTERN = '#^/api(/|$)#';
    private const EXEMPT_PATTERN = '#^/api/(me/2fa|login_check)(/|$)#';

    public function __construct(
        private readonly Security $security,
        private readonly TwoFactorMandatePolicy $twoFactorMandatePolicy,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 6]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = rawurldecode($request->getPathInfo());

        if (1 !== preg_match(self::IN_SCOPE_PATTERN, $path) || 1 === preg_match(self::EXEMPT_PATTERN, $path)) {
            return;
        }

        if ('/api/me' === $path && $request->isMethod('GET')) {
            return;
        }

        if ('/api/logout' === $path && $request->isMethod('POST')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->twoFactorMandatePolicy->needsEnforcement($user)) {
            return;
        }

        $event->setResponse(new JsonResponse(['code' => '2fa_required'], Response::HTTP_FORBIDDEN));
    }
}
