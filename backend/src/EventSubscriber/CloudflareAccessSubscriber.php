<?php

namespace App\EventSubscriber;

use App\Security\CloudflareAccessVerifier;
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
 * INACTIF si CF_ACCESS_HOSTS est vide ou si le vérificateur n'est pas
 * configuré (CF_ACCESS_TEAM_DOMAIN / CF_ACCESS_AUD) — cf. infra/README.md.
 */
final class CloudflareAccessSubscriber implements EventSubscriberInterface
{
    public const HEADER = 'Cf-Access-Jwt-Assertion';

    /** @var list<string> */
    private readonly array $protectedHosts;

    public function __construct(
        private readonly CloudflareAccessVerifier $verifier,
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
        if ([] === $this->protectedHosts || !$event->isMainRequest() || !$this->verifier->isEnabled()) {
            return;
        }

        $request = $event->getRequest();
        if (!\in_array(strtolower($request->getHost()), $this->protectedHosts, true)) {
            return;
        }

        if ($this->verifier->verify($request->headers->get(self::HEADER))) {
            return;
        }

        $event->setResponse(new Response('Forbidden', Response::HTTP_FORBIDDEN, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]));
    }
}
