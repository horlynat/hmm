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
 * `/api/me/**` avec 403 JSON `{"code":"2fa_required"}` — CONTRAT partagé
 * avec le lot frontend.
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
 * - `POST /api/logout` (route distincte, hors du préfixe `/api/me`, jamais
 *   bloquée — se déconnecter doit toujours rester possible).
 *
 * `kernel.request`, après le firewall (qui résout déjà l'utilisateur pour un
 * firewall stateless comme `api`).
 */
final class ApiTwoFactorEnforcementSubscriber implements EventSubscriberInterface
{
    private const IN_SCOPE_PATTERN = '#^/api/me(/|$)#';
    private const EXEMPT_PATTERN = '#^/api/me/2fa(/|$)#';

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
        $path = $request->getPathInfo();

        if (1 !== preg_match(self::IN_SCOPE_PATTERN, $path) || 1 === preg_match(self::EXEMPT_PATTERN, $path)) {
            return;
        }

        if ('/api/me' === $path && $request->isMethod('GET')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->twoFactorMandatePolicy->needsEnforcement($user)) {
            return;
        }

        $event->setResponse(new JsonResponse(['code' => '2fa_required'], Response::HTTP_FORBIDDEN));
    }
}
