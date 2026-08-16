<?php

namespace App\Tests\Service;

use App\Service\CurrencyConversionService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Couvre la conversion de devises pour l'affichage : jamais d'exception
 * propagée (dégrade vers le montant dans sa devise d'origine si le
 * fournisseur de taux est en panne), même philosophie que
 * GeolocationServiceTest.
 */
final class CurrencyConversionServiceTest extends TestCase
{
    private function ratesResponse(): MockResponse
    {
        return new MockResponse(json_encode([
            'result' => 'success',
            'rates' => ['EUR' => 1.0, 'USD' => 1.1, 'XAF' => 655.957],
        ]));
    }

    public function testConvertSameCurrencyIsNoOpWithoutCallingApi(): void
    {
        $httpClient = new MockHttpClient(function () {
            self::fail('Aucun appel HTTP ne doit être fait quand from === to.');
        });

        $service = new CurrencyConversionService($httpClient, new ArrayAdapter(), new NullLogger());

        self::assertSame('100.00', $service->convert('100.00', 'EUR', 'EUR'));
        self::assertSame('100.00', $service->convert('100.00', 'usd', 'USD'));
    }

    public function testConvertEurToUsd(): void
    {
        $httpClient = new MockHttpClient($this->ratesResponse());
        $service = new CurrencyConversionService($httpClient, new ArrayAdapter(), new NullLogger());

        self::assertSame('110.00', $service->convert('100.00', 'EUR', 'USD'));
    }

    public function testConvertEurToXaf(): void
    {
        $httpClient = new MockHttpClient($this->ratesResponse());
        $service = new CurrencyConversionService($httpClient, new ArrayAdapter(), new NullLogger());

        self::assertSame('65595.70', $service->convert('100.00', 'EUR', 'XAF'));
    }

    public function testConvertReturnsNullWhenProviderUnreachable(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('Connexion refusée');
        });
        $service = new CurrencyConversionService($httpClient, new ArrayAdapter(), new NullLogger());

        self::assertNull($service->convert('100.00', 'EUR', 'USD'));
    }

    public function testConvertReturnsNullForUnknownCurrency(): void
    {
        $httpClient = new MockHttpClient($this->ratesResponse());
        $service = new CurrencyConversionService($httpClient, new ArrayAdapter(), new NullLogger());

        self::assertNull($service->convert('100.00', 'EUR', 'ZZZ'));
    }

    public function testConvertCachesRatesAndDoesNotCallApiTwice(): void
    {
        $calls = 0;
        $httpClient = new MockHttpClient(function () use (&$calls) {
            ++$calls;

            return $this->ratesResponse();
        });
        $service = new CurrencyConversionService($httpClient, new ArrayAdapter(), new NullLogger());

        $service->convert('10.00', 'EUR', 'USD');
        $service->convert('20.00', 'EUR', 'XAF');

        self::assertSame(1, $calls);
    }

    public function testConvertAndFormatFallsBackToOriginalCurrencyOnFailure(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('Connexion refusée');
        });
        $service = new CurrencyConversionService($httpClient, new ArrayAdapter(), new NullLogger());

        $result = $service->convertAndFormat('100.00', 'EUR', 'USD');

        // Retombe sur le formatage de la devise d'origine (EUR), jamais une erreur.
        self::assertStringContainsString('€', $result);
    }

    public function testFormatUsesCurrencySymbol(): void
    {
        $httpClient = new MockHttpClient(function () {
            self::fail('format() ne doit faire aucun appel HTTP.');
        });
        $service = new CurrencyConversionService($httpClient, new ArrayAdapter(), new NullLogger());

        $result = $service->format('1234.50', 'USD', 'en');

        self::assertStringContainsString('1,234.50', $result);
    }

    public function testGetRatesSnapshotReturnsTheFullTable(): void
    {
        $httpClient = new MockHttpClient($this->ratesResponse());
        $service = new CurrencyConversionService($httpClient, new ArrayAdapter(), new NullLogger());

        self::assertEquals(['EUR' => 1.0, 'USD' => 1.1, 'XAF' => 655.957], $service->getRatesSnapshot());
    }

    public function testGetRatesSnapshotReturnsNullWhenProviderUnreachable(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('Connexion refusée');
        });
        $service = new CurrencyConversionService($httpClient, new ArrayAdapter(), new NullLogger());

        self::assertNull($service->getRatesSnapshot());
    }
}
