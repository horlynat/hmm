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
 * remontés pour les visiteurs connectés. Deux causes trouvées et corrigées
 * ici : (1) LoginNotificationHandler appelait ip-api.com en HTTPS, refusé en
 * 403 "SSL unavailable" par leur plan gratuit (HTTP uniquement) — supprimé,
 * consolidé sur ce service unique (ipapi.co, HTTPS) ; (2) aucun cache, donc
 * chaque vue de /profile/{id} tapait l'API en live, épuisant vite le quota
 * gratuit (constaté : 429 RateLimited depuis l'IP du VPS lui-même).
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

    public function testRateLimitedResponseReturnsNullInsteadOfThrowing(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'error' => true,
            'reason' => 'RateLimited',
        ]), ['http_code' => 429]));

        $service = new GeolocationService($httpClient, new ArrayAdapter(), new NullLogger());

        self::assertNull($service->getLocationFromIp('8.8.8.8'));
    }

    public function testNetworkFailureReturnsNullInsteadOfThrowing(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('Connexion refusée');
        });

        $service = new GeolocationService($httpClient, new ArrayAdapter(), new NullLogger());

        self::assertNull($service->getLocationFromIp('8.8.8.8'));
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
