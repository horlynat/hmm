<?php

namespace App\EventSubscriber;

use App\Security\CloudflareAccessVerifier;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Exige un jeton Cloudflare Access valide (en-tête Cf-Access-Jwt-Assertion,
 * cf. CloudflareAccessVerifier) sur les hôtes listés dans CF_ACCESS_HOSTS
 * (hôte back-office : dark.horlynat.com). Sans jeton valide : 403 nue, avant
 * routage, session et firewalls.
 *
 * INACTIF si CF_ACCESS_HOSTS est vide — cf. infra/README.md.
 *
 * Contre-audit B2 (M3) : CF_ACCESS_HOSTS renseigné SANS
 * CF_ACCESS_TEAM_DOMAIN / CF_ACCESS_AUD désactivait le contrôle en silence
 * (fail-open) — une faute de configuration suffisait à rouvrir le
 * back-office à un accès direct. Désormais fail-closed : 403 sur les hôtes
 * protégés + erreur journalisée, jusqu'à configuration complète.
 *
 * Les noms internes (`hmm-backend`, `backend`, joignables sans passer par
 * Cloudflare) ne servent plus le back-office : ils sont limités à /api,
 * /uploads, /newsletter par PublicApiHostRestrictionSubscriber
 * (PUBLIC_API_HOSTS, cf. infra/docker-compose.prod.yml).
 */
final class CloudflareAccessSubscriber implements EventSubscriberInterface
{
    public const HEADER = 'Cf-Access-Jwt-Assertion';

    /** @var list<string> */
    private readonly array $protectedHosts;

    public function __construct(
        private readonly CloudflareAccessVerifier $verifier,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::CF_ACCESS_HOSTS)%')]
        ?string $protectedHosts,
    ) {
        $this->protectedHosts = PublicApiHostRestrictionSubscriber::parseHostList($protectedHosts);
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 255]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ([] === $this->protectedHosts || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!\in_array(strtolower($request->getHost()), $this->protectedHosts, true)) {
            return;
        }

        if (!$this->verifier->isEnabled()) {
            $this->logger->error('Cloudflare Access : CF_ACCESS_HOSTS est renseigné mais CF_ACCESS_TEAM_DOMAIN / CF_ACCESS_AUD manquent — accès refusé (fail-closed).');
        } elseif ($this->verifier->verify($request->headers->get(self::HEADER))) {
            return;
        }

        $event->setResponse(new Response('Forbidden', Response::HTTP_FORBIDDEN, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]));
    }
}
