<?php

namespace App\Tests\Service;

use App\Service\GeolocationService;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Couvre le bug rapporté en prod : plus de localisation/coordonnées/pays
 * remontés pour les visiteurs connectés. Causes trouvées et corrigées ici :
 * (1) LoginNotificationHandler appelait ip-api.com en HTTPS, refusé en 403
 * "SSL unavailable" par leur plan gratuit (HTTP uniquement) — supprimé,
 * consolidé sur ce service unique ; (2) aucun cache, donc chaque vue de
 * /profile/{id} tapait l'API en live, épuisant vite le quota gratuit ipapi.co
 * (constaté en prod : 429 RateLimited, de façon prolongée, depuis l'IP de
 * sortie du VPS lui-même) ; (3) un seul fournisseur (ipapi.co) restait un
 * point de défaillance unique une fois son quota épuisé — ip-api.com (HTTP)
 * ajouté en repli.
 */
final class GeolocationServiceTest extends TestCase
{
    public function testReturnsNullForPrivateIpWithoutCallingApi(): void
    {
        $httpClient = new MockHttpClient(function () {
            self::fail('Aucun appel HTTP ne doit être fait pour une IP privée.');
        });

        $service = new GeolocationService($httpClient, new ArrayAdapter(), new NullLogger());

        self::assertNull($service->getLocationFromIp('192.168.1.10'));
        self::assertNull($service->getLocationFromIp('127.0.0.1'));
        self::assertNull($service->getLocationFromIp('::1'));
    }

    public function testReturnsNullForNullIp(): void
    {
        $httpClient = new MockHttpClient(function () {
            self::fail('Aucun appel HTTP ne doit être fait sans IP.');
        });

        $service = new GeolocationService($httpClient, new ArrayAdapter(), new NullLogger());

        self::assertNull($service->getLocationFromIp(null));
    }

    public function testParsesSuccessfulResponse(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'city' => 'Paris',
            'country_name' => 'France',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
        ])));

        $service = new GeolocationService($httpClient, new ArrayAdapter(), new NullLogger());
        $location = $service->getLocationFromIp('8.8.8.8');

        self::assertSame([
            'city' => 'Paris',
            'country_name' => 'France',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
        ], $location);
        self::assertSame('Paris, France', GeolocationService::formatLabel($location));
    }

    public function testRateLimitedOnBothProvidersReturnsNullInsteadOfThrowing(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            if (str_contains($url, 'ipapi.co')) {
                return new MockResponse(json_encode(['error' => true, 'reason' => 'RateLimited']), ['http_code' => 429]);
            }

            return new MockResponse(json_encode(['status' => 'fail', 'message' => 'RateLimited']));
        });

        $service = new GeolocationService($httpClient, new ArrayAdapter(), new NullLogger());

        self::assertNull($service->getLocationFromIp('8.8.8.8'));
    }

    public function testNetworkFailureOnBothProvidersReturnsNullInsteadOfThrowing(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('Connexion refusée');
        });

        $service = new GeolocationService($httpClient, new ArrayAdapter(), new NullLogger());

        self::assertNull($service->getLocationFromIp('8.8.8.8'));
    }

    /**
     * Reproduit exactement le cas constaté en prod : ipapi.co rate-limité
     * (429) de façon prolongée sur l'IP de sortie du VPS — la géoloc doit
     * quand même aboutir grâce au repli sur ip-api.com (HTTP).
     */
    public function testFallsBackToIpApiComWhenIpapiCoIsRateLimited(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            if (str_contains($url, 'ipapi.co')) {
                return new MockResponse(json_encode(['error' => true, 'reason' => 'RateLimited']), ['http_code' => 429]);
            }

            self::assertStringStartsWith('http://ip-api.com/', $url, 'ip-api.com doit être appelé en HTTP (leur plan gratuit refuse le HTTPS).');

            return new MockResponse(json_encode([
                'status' => 'success',
                'city' => 'Brazzaville',
                'country' => 'Congo Republic',
                'lat' => -4.2568,
                'lon' => 15.2872,
            ]));
        });

        $service = new GeolocationService($httpClient, new ArrayAdapter(), new NullLogger());

        self::assertSame([
            'city' => 'Brazzaville',
            'country_name' => 'Congo Republic',
            'latitude' => -4.2568,
            'longitude' => 15.2872,
        ], $service->getLocationFromIp('197.214.238.50'));
    }

    public function testCachesSuccessfulResultAndDoesNotCallApiTwice(): void
    {
        $calls = 0;
        $httpClient = new MockHttpClient(function () use (&$calls) {
            $calls++;

            return new MockResponse(json_encode(['city' => 'Paris', 'country_name' => 'France']));
        });

        $service = new GeolocationService($httpClient, new ArrayAdapter(), new NullLogger());

        $service->getLocationFromIp('8.8.8.8');
        $service->getLocationFromIp('8.8.8.8');

        self::assertSame(1, $calls);
    }

    public function testFormatLabelReturnsNullWithoutCity(): void
    {
        self::assertNull(GeolocationService::formatLabel(null));
        self::assertNull(GeolocationService::formatLabel(['city' => null, 'country_name' => 'France', 'latitude' => null, 'longitude' => null]));
    }
}
