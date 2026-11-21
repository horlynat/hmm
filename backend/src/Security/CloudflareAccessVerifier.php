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
 *
 * Cache des clés (contre-audit B2) :
 * - jeton signé par une clé inconnue (`kid` absent du jeu en cache, cas
 *   d'une rotation de clés chez Cloudflare) : UNE relecture forcée, au plus
 *   une par minute — sinon le back-office répondait 403 jusqu'à 1 h après
 *   une rotation, et un `kid` aléatoire ne doit pas permettre de faire
 *   interroger Cloudflare en boucle ;
 * - échec de récupération : mémorisé 30 s (sans ça, CHAQUE requête refaisait
 *   un appel bloquant jusqu'à 5 s).
 */
final class CloudflareAccessVerifier
{
    private const JWKS_CACHE_TTL = 3600;
    private const JWKS_FAILURE_TTL = 30;
    private const JWKS_MIN_REFRESH_INTERVAL = 60;

    /** Avertissement « verrou inaccessible » émis au plus une fois par processus. */
    private static bool $lockWarningLogged = false;
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
        // Dossier du verrou de relecture (défaut : dossier temporaire du
        // système). Paramétrable pour les tests.
        private readonly ?string $lockDir = null,
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
            try {
                $claims = $this->decode($assertion, $this->keySet());
            } catch (\UnexpectedValueException $e) {
                // Clé inconnue (rotation chez Cloudflare) : une relecture
                // forcée, bornée dans le temps, puis nouvel essai unique.
                if (!str_contains($e->getMessage(), '"kid" invalid') || !$this->refreshKeySet()) {
                    throw $e;
                }
                $claims = $this->decode($assertion, $this->keySet());
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
     * @param array<string, \Firebase\JWT\Key> $keys
     *
     * @return array<string, mixed>
     */
    private function decode(string $assertion, array $keys): array
    {
        $previousLeeway = JWT::$leeway;
        JWT::$leeway = self::LEEWAY;
        try {
            return (array) JWT::decode($assertion, $keys);
        } finally {
            JWT::$leeway = $previousLeeway;
        }
    }

    private function cacheKey(): string
    {
        return 'cf_access_jwks_'.hash('xxh128', $this->teamDomain);
    }

    /**
     * Relit le jeu de clés, au plus une fois par minute, et ne remplace le
     * cache QUE si la relecture réussit.
     *
     * Contre-audit B2 (3e passe) :
     * - 2a : le créneau était réservé avec `time()` — toutes les requêtes de
     *   la même seconde « gagnaient » et relançaient chacune un appel
     *   bloquant (20 kid aléatoires simultanés = 21 lectures). Réservation
     *   par jeton aléatoire : une seule requête la gagne par minute.
     * - 2b : le cache était vidé AVANT de savoir si la relecture réussirait —
     *   pendant une panne de Cloudflare, un seul kid aléatoire faisait
     *   perdre les clés valides (jetons légitimes refusés). On lit d'abord,
     *   on remplace ensuite ; en cas d'échec, les anciennes clés restent.
     *
     * @return bool true si de nouvelles clés ont été chargées
     */
    private function refreshKeySet(): bool
    {
        // Contre-audit B2 (5e passe, C1) : sans verrou, une requête qui lisait
        // « pas de réservation » juste avant l'écriture du gagnant la gagnait
        // aussi (1 à 4 relectures sur 80 processus simultanés). Verrou de
        // fichier non bloquant : un seul processus vérifie PUIS pose la
        // réservation ; les autres repartent aussitôt, sans attendre.
        $lockPath = rtrim($this->lockDir ?? sys_get_temp_dir(), '/').'/'.$this->cacheKey().'.lock';
        $handle = @fopen($lockPath, 'c');
        if (false === $handle) {
            // Jamais silencieux : sans ce verrou, une rotation de clés n'est
            // plus absorbée qu'à l'expiration du cache (jusqu'à 1 h de 403).
            // Une seule fois par processus : un kid aléatoire par requête ne
            // doit pas pouvoir inonder les logs.
            if (!self::$lockWarningLogged) {
                self::$lockWarningLogged = true;
                $this->logger->warning('Cloudflare Access : verrou de relecture des clés inaccessible, relecture forcée désactivée.', ['path' => $lockPath]);
            }

            return false;
        }

        try {
            if (!flock($handle, \LOCK_EX | \LOCK_NB)) {
                return false;
            }

            $won = false;
            $this->cache->get($this->cacheKey().'_refresh', function (ItemInterface $item) use (&$won): bool {
                $item->expiresAfter(self::JWKS_MIN_REFRESH_INTERVAL);
                $won = true;

                return true;
            });

            if (!$won) {
                return false;
            }

            return $this->replaceKeySet();
        } finally {
            flock($handle, \LOCK_UN);
            fclose($handle);
        }
    }

    private function replaceKeySet(): bool
    {
        $keys = $this->fetchKeys();
        if (null === $keys) {
            return false;
        }

        // beta = INF : recalcul forcé, l'entrée en cache est remplacée.
        $this->cache->get($this->cacheKey(), function (ItemInterface $item) use ($keys): array {
            $item->expiresAfter(self::JWKS_CACHE_TTL);

            return ['keys' => $keys];
        }, \INF);

        return true;
    }

    /**
     * Récupère et VALIDE le jeu de clés avant toute mise en cache.
     *
     * Contre-audit B2 (5e passe, C2) :
     * - seules les clés RSA dont `alg` est absent ou vaut RS256 sont gardées :
     *   JWK::parseKeySet(…, 'RS256') n'impose RS256 qu'aux clés SANS `alg` —
     *   une clé `oct` déclarée HS256 faisait accepter un jeton HS256 ;
     * - le jeu est décodé ici (parseKeySet) : une clé malformée mêlée aux
     *   bonnes remplaçait le cache et faisait refuser tous les jetons pendant
     *   1 h. Désormais, rien de valide = échec (les anciennes clés restent).
     *
     * @return list<array<string, mixed>>|null null si la récupération ou la validation échoue
     */
    private function fetchKeys(): ?array
    {
        try {
            $response = $this->httpClient->request('GET', sprintf('https://%s/cdn-cgi/access/certs', $this->teamDomain), [
                'timeout' => 5,
                'max_redirects' => 0,
            ]);

            $data = $response->toArray();
            if (!isset($data['keys']) || !\is_array($data['keys'])) {
                throw new \RuntimeException('JWKS Cloudflare Access invalide.');
            }

            $keys = [];
            foreach ($data['keys'] as $jwk) {
                if (!\is_array($jwk) || 'RSA' !== ($jwk['kty'] ?? null) || !\is_string($jwk['kid'] ?? null)) {
                    continue;
                }
                if (isset($jwk['alg']) && 'RS256' !== $jwk['alg']) {
                    continue;
                }
                try {
                    JWK::parseKeySet(['keys' => [$jwk]], 'RS256');
                } catch (\Throwable) {
                    continue;
                }
                $keys[] = $jwk;
            }

            if ([] === $keys) {
                throw new \RuntimeException('JWKS Cloudflare Access : aucune clé RS256 valide.');
            }

            return $keys;
        } catch (\Throwable $e) {
            $this->logger->error('Cloudflare Access : récupération des clés impossible.', ['reason' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<string, \Firebase\JWT\Key>
     */
    private function keySet(): array
    {
        $jwks = $this->cache->get($this->cacheKey(), function (ItemInterface $item): array {
            $keys = $this->fetchKeys();
            if (null === $keys) {
                // Échec mémorisé brièvement : pas un appel bloquant par requête.
                $item->expiresAfter(self::JWKS_FAILURE_TTL);

                return ['error' => true];
            }

            $item->expiresAfter(self::JWKS_CACHE_TTL);

            return ['keys' => $keys];
        });

        if (!isset($jwks['keys'])) {
            throw new \RuntimeException('JWKS Cloudflare Access indisponible.');
        }

        // RS256 imposé : seules des clés RSA sans `alg` ou en RS256 sont
        // mises en cache (fetchKeys), et RS256 est la valeur par défaut des
        // clés sans `alg` — jamais l'algorithme annoncé par le jeton.
        return JWK::parseKeySet(['keys' => $jwks['keys']], 'RS256');
    }
}
