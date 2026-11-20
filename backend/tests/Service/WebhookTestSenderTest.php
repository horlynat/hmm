<?php

namespace App\Tests\Service;

use App\Service\WebhookTestSender;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * SSRF via le test de webhook (audit 2026-11-20, P2) : la vérification
 * préalable du contrôleur (gethostbyname) se contournait par redirection
 * ou DNS rebinding.
 */
final class WebhookTestSenderTest extends TestCase
{
    public function testPublicWebhookSucceeds(): void
    {
        $client = new MockHttpClient(new MockResponse('ok', ['http_code' => 200, 'primary_ip' => '93.184.215.14']));

        self::assertTrue((new WebhookTestSender($client))->send('https://93.184.215.14/hook', 'x'));
    }

    /** @return iterable<string, array{0: string}> */
    public static function internalTargets(): iterable
    {
        yield 'loopback' => ['https://127.0.0.1/hook'];
        yield 'métadonnées cloud' => ['https://169.254.169.254/latest/meta-data/'];
        yield 'RFC1918' => ['https://10.0.0.5/hook'];
        yield 'IPv6 loopback' => ['https://[::1]/hook'];
        yield 'IPv4 encapsulée' => ['https://[::ffff:127.0.0.1]/hook'];
    }

    #[DataProvider('internalTargets')]
    public function testInternalTargetIsNeverRequested(string $url): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            self::fail('Aucune requête ne doit partir vers un hôte interne.');
        });

        self::assertFalse((new WebhookTestSender($client))->send($url, 'x'));
    }

    /**
     * DNS rebinding : le nom résolvait vers une IP publique au contrôle,
     * la connexion réelle aboutit sur une IP interne.
     */
    public function testConnectionLandingOnAPrivateIpIsAborted(): void
    {
        $client = new MockHttpClient(new MockResponse('secret', ['http_code' => 200, 'primary_ip' => '10.1.2.3']));

        self::assertFalse((new WebhookTestSender($client))->send('https://93.184.215.14/hook', 'x'));
    }

    public function testRedirectsAreNotFollowed(): void
    {
        $calls = 0;
        $client = new MockHttpClient(static function (string $method, string $url) use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse('', ['http_code' => 302, 'primary_ip' => '93.184.215.14', 'redirect_url' => 'https://93.184.215.15/elsewhere', 'response_headers' => ['Location' => 'https://93.184.215.15/elsewhere']]);
        });

        self::assertFalse((new WebhookTestSender($client))->send('https://93.184.215.14/hook', 'x'));
        self::assertSame(1, $calls, 'Une seule requête : la redirection est ignorée.');
    }
}
