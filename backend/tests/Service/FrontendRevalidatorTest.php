<?php

namespace App\Tests\Service;

use App\Service\FrontendRevalidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FrontendRevalidatorTest extends TestCase
{
    public function testSkipsCallEntirelyWhenNoSecretConfigured(): void
    {
        $httpClient = new MockHttpClient(function () {
            self::fail('Aucun appel HTTP ne doit être fait sans secret configuré.');
        });

        $revalidator = new FrontendRevalidator($httpClient, new NullLogger(), 'https://horlynat.com');

        self::assertFalse($revalidator->isConfigured());
        $revalidator->revalidate('projects');
    }

    public function testCallsWebhookWithTagSecretAndTrailingSlashStripped(): void
    {
        $capturedUrl = null;
        $capturedHeaders = null;
        $capturedBody = null;

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedHeaders, &$capturedBody) {
            $capturedUrl = $url;
            $capturedHeaders = $options['headers'];
            $capturedBody = $options['body'];

            self::assertSame('POST', $method);

            return new MockResponse(json_encode(['revalidated' => true, 'tag' => 'projects']));
        });

        $revalidator = new FrontendRevalidator($httpClient, new NullLogger(), 'https://horlynat.com/', 'shh-secret');

        self::assertTrue($revalidator->isConfigured());
        $revalidator->revalidate('projects');

        self::assertSame('https://horlynat.com/api/revalidate', $capturedUrl);
        self::assertContains('x-revalidate-secret: shh-secret', $capturedHeaders);
        self::assertSame('{"tag":"projects"}', $capturedBody);
    }

    public function testHttpErrorResponseIsLoggedButDoesNotThrow(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('Unauthorized', ['http_code' => 401]));
        $revalidator = new FrontendRevalidator($httpClient, new NullLogger(), 'https://horlynat.com', 'wrong-secret');

        // Ne doit lever aucune exception : une erreur HTTP "logique" (secret
        // invalide...) n'est pas transitoire, inutile de la faire remonter à
        // Messenger pour un retry qui échouerait de la même façon.
        $revalidator->revalidate('projects');
        $this->expectNotToPerformAssertions();
    }

    public function testNetworkFailureIsRethrownForMessengerRetry(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('Connexion refusée');
        });
        $revalidator = new FrontendRevalidator($httpClient, new NullLogger(), 'https://horlynat.com', 'shh-secret');

        $this->expectException(\Throwable::class);
        $revalidator->revalidate('projects');
    }

    public function testWarnsWhenFrontendUrlIsNotHttps(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode(['revalidated' => true])));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $revalidator = new FrontendRevalidator($httpClient, $logger, 'http://localhost:3000', 'shh-secret');
        $revalidator->revalidate('projects');
    }

    public function testDoesNotWarnWhenFrontendUrlIsHttps(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode(['revalidated' => true])));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $revalidator = new FrontendRevalidator($httpClient, $logger, 'https://horlynat.com', 'shh-secret');
        $revalidator->revalidate('projects');
    }
}
