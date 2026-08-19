<?php

namespace App\EventSubscriber;

use App\Repository\BlockedIpRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Refuse toute tentative de connexion depuis une IP bloquée (cf.
 * App\Entity\BlockedIp, posée manuellement par un admin depuis
 * AdminSecurityPolicyController) — AVANT même que l'authentification ne
 * démarre, sur kernel.request plutôt que via un listener Security
 * (CheckPassportEvent, UserChecker...) : ces derniers ne couvrent chacun
 * qu'UN SEUL firewall (le UserChecker post-auth ne voit même pas l'IP), alors
 * qu'ici on veut couvrir uniformément le formulaire web ET l'API JWT sans
 * dupliquer la logique dans les deux configurations.
 *
 * Distinct du rate-limiter Symfony existant (5 tentatives/minute/IP,
 * automatique et temporaire, cf. SecurityAuthenticator::authenticate()) :
 * ceci est une décision manuelle, persistante jusqu'à déblocage explicite.
 */
final class IpBlockSubscriber implements EventSubscriberInterface
{
    /**
     * Chemins d'authentification à couvrir — formulaire web, vérification 2FA
     * web, login API JWT, vérification 2FA API (cf. debug:router).
     *
     * @var string[]
     */
    private const PROTECTED_PATHS = ['/login', '/2fa_check', '/api/login_check', '/api/login_check/2fa'];

    public function __construct(private readonly BlockedIpRepository $blockedIpRepository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // Priorité haute : passe avant le firewall Security (qui tenterait
        // sinon d'authentifier la requête pour rien) et avant le rate-limiter.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!self::isProtectedLoginAttempt($request->getMethod(), $request->getPathInfo())) {
            return;
        }

        $ip = $request->getClientIp();
        if (null === $ip || !$this->blockedIpRepository->isBlocked($ip)) {
            return;
        }

        $event->setResponse(new Response(
            'Cette adresse IP a été bloquée suite à une activité suspecte. Contactez un administrateur si vous pensez qu\'il s\'agit d\'une erreur.',
            Response::HTTP_FORBIDDEN,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        ));
    }

    /**
     * Extrait pour être testable sans monter tout le kernel HTTP.
     */
    public static function isProtectedLoginAttempt(string $method, string $pathInfo): bool
    {
        return 'POST' === strtoupper($method) && in_array($pathInfo, self::PROTECTED_PATHS, true);
    }
}
