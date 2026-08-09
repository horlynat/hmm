<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Géolocalisation IP avec repli : ipapi.co (HTTPS) en premier, puis
 * ip-api.com (HTTP — leur plan gratuit refuse le HTTPS avec un 403 "SSL
 * unavailable", bug constaté dans l'ancienne implémentation propre à
 * LoginNotificationHandler ; HTTP en clair volontaire ici, cf. fetchFromIpApiCom()).
 * Constaté en prod : le quota gratuit ipapi.co se fait rate-limiter (429) de
 * façon prolongée depuis l'IP de sortie du VPS — le simple cache ne suffit
 * pas à masquer une panne fournisseur qui dure, d'où ce second fournisseur en
 * secours plutôt qu'un seul point de défaillance. Résultat mis en cache par
 * IP et échecs journalisés (au lieu d'être avalés silencieusement) pour
 * rester diagnosticable.
 */
class GeolocationService
{
    private const SUCCESS_TTL = 21600; // 6h : une IP ne change pas de ville d'une requête à l'autre.
    private const FAILURE_TTL = 900; // 15 min : réessaie vite après un rate-limit/incident ipapi.co sans le marteler.

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{city: ?string, country_name: ?string, latitude: ?float, longitude: ?float}|null
     *         null pour une IP absente/locale/privée (aucun appel externe, sans objet) ou en cas d'échec.
     */
    public function getLocationFromIp(?string $ip): ?array
    {
        if (null === $ip || !$this->isPublicIp($ip)) {
            return null;
        }

        return $this->cache->get('geoloc_ip_' . sha1($ip), function (ItemInterface $item) use ($ip) {
            $location = $this->fetch($ip);
            $item->expiresAfter(null !== $location ? self::SUCCESS_TTL : self::FAILURE_TTL);

            return $location;
        });
    }

    public function isPublicIp(string $ip): bool
    {
        return false !== filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * @return array{city: ?string, country_name: ?string, latitude: ?float, longitude: ?float}|null
     */
    private function fetch(string $ip): ?array
    {
        return $this->fetchFromIpapiCo($ip) ?? $this->fetchFromIpApiCom($ip);
    }

    /**
     * @return array{city: ?string, country_name: ?string, latitude: ?float, longitude: ?float}|null
     */
    private function fetchFromIpapiCo(string $ip): ?array
    {
        try {
            $data = $this->httpClient->request('GET', "https://ipapi.co/{$ip}/json/", ['timeout' => 5])->toArray();
        } catch (\Throwable $e) {
            $this->logger->info('Géolocalisation IP : ipapi.co indisponible, repli sur ip-api.com', ['ip' => $ip, 'error' => $e->getMessage()]);

            return null;
        }

        if (!empty($data['error'])) {
            $this->logger->info('Géolocalisation IP : ipapi.co en erreur, repli sur ip-api.com', [
                'ip' => $ip,
                'reason' => $data['reason'] ?? null,
            ]);

            return null;
        }

        return [
            'city' => $data['city'] ?? null,
            'country_name' => $data['country_name'] ?? null,
            'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
        ];
    }

    /**
     * Repli quand ipapi.co échoue. Le plan gratuit d'ip-api.com refuse le
     * HTTPS (403 "SSL unavailable, order a key..."), HTTP est donc requis et
     * volontaire ici — aucune donnée sensible ne transite en clair : seule
     * l'IP du visiteur (déjà connue de nous) part dans l'URL, et la réponse
     * ne nourrit qu'un affichage informatif admin, jamais une décision de
     * sécurité (contrairement à getClientIp(), jamais concerné par cet appel).
     *
     * @return array{city: ?string, country_name: ?string, latitude: ?float, longitude: ?float}|null
     */
    private function fetchFromIpApiCom(string $ip): ?array
    {
        try {
            $data = $this->httpClient->request('GET', "http://ip-api.com/json/{$ip}", ['timeout' => 5])->toArray(false);
        } catch (\Throwable $e) {
            $this->logger->warning('Géolocalisation IP échouée (ipapi.co et ip-api.com)', ['ip' => $ip, 'error' => $e->getMessage()]);

            return null;
        }

        if ('success' !== ($data['status'] ?? null)) {
            $this->logger->warning('Géolocalisation IP échouée (ipapi.co et ip-api.com)', ['ip' => $ip, 'status' => $data['status'] ?? null]);

            return null;
        }

        return [
            'city' => $data['city'] ?? null,
            'country_name' => $data['country'] ?? null,
            'latitude' => isset($data['lat']) ? (float) $data['lat'] : null,
            'longitude' => isset($data['lon']) ? (float) $data['lon'] : null,
        ];
    }

    /**
     * Formatte un résultat de getLocationFromIp() en libellé lisible ("Ville, Pays"),
     * partagé par tous les appelants pour ne pas dupliquer la règle de jointure.
     *
     * @param array{city: ?string, country_name: ?string, latitude: ?float, longitude: ?float}|null $location
     */
    public static function formatLabel(?array $location): ?string
    {
        if (null === $location || empty($location['city'])) {
            return null;
        }

        return trim($location['city'] . (!empty($location['country_name']) ? ', ' . $location['country_name'] : ''));
    }
}
