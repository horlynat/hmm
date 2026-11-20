<?php

namespace App\Security;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Vérifie le jeton `Cf-Access-Jwt-Assertion` que Cloudflare Access ajoute à
 * chaque requête qu'il laisse passer (P1 #15) : signature RS256 contre les
 * clés publiques de l'équipe Zero Trust (https://<équipe>/cdn-cgi/access/certs),
 * émetteur = https://<équipe>, audience = AUD de l'application Access,
 * expiration/nbf.
 *
 * But : qu'un accès DIRECT à l'origine (IP du VPS connue, routeur Traefik mal
 * configuré, en-tête Host forgé…) ne suffise jamais à atteindre le
 * back-office — il faut avoir réellement franchi Cloudflare Access.
 *
 * INACTIF tant que CF_ACCESS_TEAM_DOMAIN ou CF_ACCESS_AUD est vide.
 */
final class CloudflareAccessVerifier
{
    private const JWKS_CACHE_TTL = 3600;
    /** Tolérance d'horloge (secondes) sur exp/nbf/iat. */
    private const LEEWAY = 30;

    private readonly string $teamDomain;
    /** @var list<string> */
    private readonly array $audiences;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::CF_ACCESS_TEAM_DOMAIN)%')]
        ?string $teamDomain,
        #[Autowire('%env(default::CF_ACCESS_AUD)%')]
        ?string $audiences,
    ) {
        $domain = strtolower(trim((string) $teamDomain));
        $domain = (string) preg_replace('#^https?://#', '', $domain);
        $this->teamDomain = rtrim($domain, '/');
        $this->audiences = array_values(array_filter(array_map('trim', explode(',', (string) $audiences))));
    }

    public function isEnabled(): bool
    {
        return '' !== $this->teamDomain && [] !== $this->audiences;
    }

    /**
     * @return bool true si le jeton est authentique, non expiré et destiné à
     *              l'une des audiences configurées
     */
    public function verify(?string $assertion): bool
    {
        if (null === $assertion || '' === $assertion || \strlen($assertion) > 8192) {
            return false;
        }

        try {
            $keys = $this->keySet();
            $previousLeeway = JWT::$leeway;
            JWT::$leeway = self::LEEWAY;
            try {
                $claims = (array) JWT::decode($assertion, $keys);
            } finally {
                JWT::$leeway = $previousLeeway;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Cloudflare Access : jeton refusé.', ['reason' => $e->getMessage()]);

            return false;
        }

        if (($claims['iss'] ?? null) !== 'https://'.$this->teamDomain) {
            $this->logger->warning('Cloudflare Access : émetteur inattendu.', ['iss' => $claims['iss'] ?? null]);

            return false;
        }

        $tokenAudiences = (array) ($claims['aud'] ?? []);
        foreach ($tokenAudiences as $aud) {
            if (\is_string($aud) && \in_array($aud, $this->audiences, true)) {
                return true;
            }
        }

        $this->logger->warning('Cloudflare Access : audience inattendue.');

        return false;
    }

    /**
     * @return array<string, \Firebase\JWT\Key>
     */
    private function keySet(): array
    {
        $jwks = $this->cache->get('cf_access_jwks_'.hash('xxh128', $this->teamDomain), function (ItemInterface $item): array {
            $item->expiresAfter(self::JWKS_CACHE_TTL);
            $response = $this->httpClient->request('GET', sprintf('https://%s/cdn-cgi/access/certs', $this->teamDomain), [
                'timeout' => 5,
                'max_redirects' => 0,
            ]);

            $data = $response->toArray();
            if (!isset($data['keys']) || !\is_array($data['keys']) || [] === $data['keys']) {
                throw new \RuntimeException('JWKS Cloudflare Access vide.');
            }

            return ['keys' => $data['keys']];
        });

        // RS256 imposé : jamais l'algorithme annoncé par le jeton lui-même.
        return JWK::parseKeySet($jwks, 'RS256');
    }
}
