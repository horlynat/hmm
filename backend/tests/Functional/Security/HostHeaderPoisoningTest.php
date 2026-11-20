<?php

namespace App\Tests\Functional\Security;

use App\Service\MailLinkGenerator;
use App\Tests\Functional\FunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * P0 #3 — empoisonnement des liens e-mail via X-Forwarded-Host / Host.
 *
 * Attaque reproduite : POST /api/forgot-password anonyme sur un compte
 * administrateur, avec un en-tête X-Forwarded-Host (et/ou Host, et/ou
 * X-Forwarded-Port) pointant vers un domaine tiers, depuis une IP que
 * l'application considère comme un proxy de confiance. Aucun lien produit ne
 * doit contenir l'hôte de l'attaquant.
 */
final class HostHeaderPoisoningTest extends FunctionalTestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        parent::tearDown();
    }

    public function testForgedForwardedHostNeverReachesAdminResetLink(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);

        // Le client EST un proxy de confiance (pire cas : requête relayée par
        // Traefik, qui conserve l'en-tête fourni par le visiteur).
        Request::setTrustedProxies([$this->clientIp], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);

        $response = $this->jsonRequest('POST', '/api/forgot-password', ['email' => $admin->getEmail()], null, [
            'HTTP_X_FORWARDED_HOST' => 'evil.tld',
            'HTTP_X_FORWARDED_PORT' => '4443',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        self::assertSame(200, $response->getStatusCode());

        $emails = $this->capturedEmails();
        self::assertNotEmpty($emails, 'Un e-mail de réinitialisation doit avoir été produit pour le compte admin.');
        foreach ($emails as $email) {
            self::assertStringNotContainsString('evil.tld', $email);
            self::assertStringNotContainsString('4443', $email);
        }
        self::assertStringContainsString('reinitialiser-mot-de-passe', implode("\n", $emails));
    }

    public function testForgedHostHeaderNeverReachesAdminResetLink(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);

        // Variante sans proxy : le Host lui-même est forgé (accès direct à
        // l'origine, ou trusted_hosts non configuré).
        // URI absolue : BrowserKit dérive Host de l'URI (un HTTP_HOST passé en
        // paramètre serveur serait écrasé).
        $this->jsonRequest('POST', 'http://evil.tld/api/forgot-password', ['email' => $admin->getEmail()]);
        self::assertSame('evil.tld', $this->client->getRequest()->getHost());

        $emails = implode("\n", $this->capturedEmails());
        self::assertStringContainsString('reinitialiser-mot-de-passe', $emails);
        self::assertStringNotContainsString('evil.tld', $emails);
    }

    public function testNewsletterLinksIgnoreForgedHost(): void
    {
        Request::setTrustedProxies([$this->clientIp], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);

        $this->client->request('POST', 'http://evil.tld/api/newsletter_subscribers', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_X_FORWARDED_HOST' => 'evil2.tld',
        ], json_encode(['email' => sprintf('nl-%s@test.invalid', bin2hex(random_bytes(5))), 'locale' => 'fr'], \JSON_THROW_ON_ERROR));
        self::assertLessThan(300, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());

        $emails = implode("\n", $this->capturedEmails());
        self::assertStringContainsString('newsletter/confirm/', $emails);
        self::assertStringNotContainsString('evil', $emails);
    }

    public function testForwardedHostAndPortAreNoLongerTrustedHeaders(): void
    {
        // Même derrière un proxy de confiance, Symfony ne lit plus ces deux en-têtes.
        Request::setTrustedProxies(['10.0.0.1'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
        $request = Request::create('http://api.example.test/api/me', server: [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_HOST' => 'evil.tld',
            'HTTP_X_FORWARDED_PORT' => '4443',
        ]);
        self::assertSame('api.example.test', $request->getHost());
        self::assertSame(80, $request->getPort());

        self::bootKernel();
        $headers = static::getContainer()->getParameter('kernel.trusted_headers');
        self::assertIsArray($headers);
        self::assertNotContains('x-forwarded-host', $headers);
        self::assertNotContains('x-forwarded-port', $headers);
    }

    public function testMailLinkGeneratorRejectsNonAbsoluteBase(): void
    {
        $this->expectException(\LogicException::class);
        MailLinkGenerator::contextFromBaseUrl('//evil.tld');
    }

    public function testTrustedHostsRejectsUnknownHostWhenConfigured(): void
    {
        $previous = Request::getTrustedHosts();
        try {
            Request::setTrustedHosts(['^(api|dark)\.horlynat\.com$', '^backend$']);
            $legit = Request::create('https://api.horlynat.com/api/me');
            self::assertSame('api.horlynat.com', $legit->getHost());

            $internal = Request::create('http://backend/api/me');
            self::assertSame('backend', $internal->getHost());

            $evil = Request::create('https://api.horlynat.com.evil.tld/api/me');
            $this->expectException(\Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException::class);
            $evil->getHost();
        } finally {
            Request::setTrustedHosts($previous);
        }
    }
}
