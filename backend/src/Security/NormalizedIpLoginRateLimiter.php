<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;

/**
 * Décore le limiteur `login_throttling` natif de Symfony (firewall
 * api_login, cf. config/services.yaml) pour qu'il compte par préfixe IPv6
 * /64 plutôt que par adresse exacte (cf. ClientIpRateLimitKey).
 *
 * DefaultLoginRateLimiter lit lui-même `$request->getClientIp()` : on lui
 * passe une copie de la requête dont l'IP client résolue (après prise en
 * compte des proxys de confiance) est remplacée par sa clé normalisée, sans
 * plus aucun en-tête de transfert — la copie n'est jamais utilisée ailleurs.
 */
final class NormalizedIpLoginRateLimiter implements RequestRateLimiterInterface
{
    public function __construct(private readonly RequestRateLimiterInterface $inner)
    {
    }

    public function consume(Request $request): RateLimit
    {
        return $this->inner->consume($this->normalize($request));
    }

    public function reset(Request $request): void
    {
        $this->inner->reset($this->normalize($request));
    }

    private function normalize(Request $request): Request
    {
        $key = ClientIpRateLimitKey::fromIp($request->getClientIp());

        $copy = $request->duplicate();
        foreach (['X-Forwarded-For', 'X-Forwarded-Host', 'X-Forwarded-Proto', 'X-Forwarded-Port', 'X-Forwarded-Prefix', 'Forwarded'] as $header) {
            $copy->headers->remove($header);
        }
        $copy->server->set('REMOTE_ADDR', $key);
        // Les attributs portent l'utilisateur ciblé (clé locale `username-ip`) : conservés par duplicate().

        return $copy;
    }
}
