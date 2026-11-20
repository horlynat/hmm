<?php

namespace App\Tests\Service;

use App\Service\MailLinkGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Contre-audit P0 #3 : en prod, la base des liens back-office ne retombe
 * plus silencieusement sur DEFAULT_URI (hôte API) — échec franc.
 */
final class MailLinkGeneratorTest extends KernelTestCase
{
    private function generator(?string $backOffice, ?string $apiHosts, string $env): MailLinkGenerator
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = static::getContainer()->get('router');

        return new MailLinkGenerator($router, 'https://api.horlynat.com', $backOffice, $apiHosts, $env);
    }

    public function testProdRefusesEmptyBackOfficeUrl(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/BACKOFFICE_URL doit être défini/');
        $this->generator('', 'api.horlynat.com', 'prod')->backOfficeUrl('login');
    }

    public function testProdRefusesNullBackOfficeUrl(): void
    {
        $this->expectException(\LogicException::class);
        $this->generator(null, 'api.horlynat.com', 'prod')->backOfficeUrl('login');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function apiHostBases(): iterable
    {
        yield 'identique' => ['https://api.horlynat.com'];
        yield 'casse + point final + chemin' => ['https://API.horlynat.com./x'];
        yield 'port' => ['https://api.horlynat.com:8443'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('apiHostBases')]
    public function testProdRefusesBackOfficeUrlOnApiHost(string $base): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/PUBLIC_API_HOSTS/');
        $this->generator($base, 'api.horlynat.com', 'prod')->backOfficeUrl('login');
    }

    public function testProdUsesConfiguredBackOfficeUrl(): void
    {
        $url = $this->generator('https://dark.horlynat.com', 'api.horlynat.com', 'prod')->backOfficeUrl('password_reset', ['token' => 'abc']);
        self::assertSame('https://dark.horlynat.com/reinitialiser-mot-de-passe/abc', $url);
    }

    public function testOutsideProdFallsBackToDefaultUri(): void
    {
        foreach (['dev', 'test'] as $env) {
            $url = $this->generator('', 'api.horlynat.com', $env)->backOfficeUrl('login');
            self::assertSame('https://api.horlynat.com/login', $url, $env);
        }
    }

    public function testPublicLinksUnaffected(): void
    {
        $url = $this->generator('', null, 'prod')->publicUrl('newsletter_confirm', ['token' => 't']);
        self::assertSame('https://api.horlynat.com/newsletter/confirm/t', $url);
    }
}
