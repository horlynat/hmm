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

    /** Base des liens back-office en test (.env.test), distincte de DEFAULT_URI. */
    private const BACKOFFICE_BASE = 'https://backoffice.test.invalid';

    /**
     * Déclare $ip comme proxy de confiance avec EXACTEMENT les en-têtes que
     * l'application déclare de confiance (paramètre kernel.trusted_headers,
     * converti comme le fait Kernel::boot()) — jamais une liste écrite à la
     * main dans le test : si framework.trusted_headers redevient laxiste,
     * ces tests le voient.
     */
    private function trustAsProxyWithAppTrustedHeaders(string $ip): int
    {
        $headers = static::getContainer()->getParameter('kernel.trusted_headers');
        if (\is_string($headers)) {
            $headers = array_map('trim', explode(',', $headers));
        }

        if (\is_int($headers)) {
            $mask = $headers;
        } else {
            self::assertIsArray($headers);
            $mask = 0;
            foreach ($headers as $header) {
                $mask |= \constant(Request::class.'::HEADER_'.strtr(strtoupper((string) $header), '-', '_'));
            }
        }

        Request::setTrustedProxies([$ip], $mask);

        return $mask;
    }

    /**
     * @return list<string> URLs de réinitialisation présentes dans les e-mails capturés
     */
    private function resetLinks(): array
    {
        preg_match_all('#https?://[^\s"\'<>]+reinitialiser-mot-de-passe/[A-Za-z0-9._\-]+#', implode("\n", $this->capturedEmails()), $m);

        return array_values(array_unique($m[0]));
    }

    public function testForgedForwardedHostNeverReachesAdminResetLink(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);

        // Le client EST un proxy de confiance (pire cas : requête relayée par
        // Traefik, qui conserve l'en-tête fourni par le visiteur), avec les
        // en-têtes de confiance RÉELS de l'application.
        $this->trustAsProxyWithAppTrustedHeaders($this->clientIp);

        $response = $this->jsonRequest('POST', 'http://api.horlynat.test/api/forgot-password', ['email' => $admin->getEmail()], null, [
            'HTTP_X_FORWARDED_HOST' => 'evil.tld',
            'HTTP_X_FORWARDED_PORT' => '4443',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        self::assertSame(200, $response->getStatusCode());

        // 1) L'application ne croit pas les en-têtes forgés (trusted_headers).
        self::assertSame('api.horlynat.test', $this->client->getRequest()->getHost(), 'X-Forwarded-Host ne doit jamais devenir l\'hôte vu par Symfony.');
        self::assertSame(443, $this->client->getRequest()->getPort(), 'X-Forwarded-Port ne doit pas être lu (seul X-Forwarded-Proto l\'est).');

        // 2) Le lien envoyé est construit sur la base back-office configurée.
        $links = $this->resetLinks();
        self::assertCount(1, $links, 'Exactement un lien de réinitialisation attendu pour le compte admin.');
        self::assertStringStartsWith(self::BACKOFFICE_BASE.'/reinitialiser-mot-de-passe/', $links[0]);

        foreach ($this->capturedEmails() as $email) {
            self::assertStringNotContainsString('evil.tld', $email);
            self::assertStringNotContainsString('4443', $email);
        }
    }

    public function testForgedHostHeaderNeverReachesAdminResetLink(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);

        // Variante sans proxy : le Host lui-même est forgé (accès direct à
        // l'origine, ou trusted_hosts non configuré). URI absolue : BrowserKit
        // dérive Host de l'URI (un HTTP_HOST passé en paramètre serait écrasé).
        $this->jsonRequest('POST', 'http://evil.tld/api/forgot-password', ['email' => $admin->getEmail()]);
        self::assertSame('evil.tld', $this->client->getRequest()->getHost());

        $links = $this->resetLinks();
        self::assertCount(1, $links);
        self::assertStringStartsWith(self::BACKOFFICE_BASE.'/reinitialiser-mot-de-passe/', $links[0]);
        self::assertStringNotContainsString('evil.tld', implode("\n", $this->capturedEmails()));
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
        // Avec les en-têtes de confiance RÉELS de l'application (pas une liste
        // écrite ici), un proxy de confiance ne peut toujours pas imposer
        // l'hôte ni le port ; X-Forwarded-For/-Proto, eux, restent lus.
        $mask = $this->trustAsProxyWithAppTrustedHeaders('10.0.0.1');
        self::assertSame(0, $mask & Request::HEADER_X_FORWARDED_HOST, 'x-forwarded-host ne doit pas être un en-tête de confiance.');
        self::assertSame(0, $mask & Request::HEADER_X_FORWARDED_PORT, 'x-forwarded-port ne doit pas être un en-tête de confiance.');

        $request = Request::create('http://api.example.test/api/me', server: [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_HOST' => 'evil.tld',
            'HTTP_X_FORWARDED_PORT' => '4443',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.7',
        ]);
        self::assertSame('api.example.test', $request->getHost());
        self::assertSame(443, $request->getPort());
        self::assertSame('https', $request->getScheme(), 'X-Forwarded-Proto doit rester lu (sinon liens/cookies en http derrière Traefik).');
        self::assertSame('203.0.113.7', $request->getClientIp(), 'X-Forwarded-For doit rester lu (rate limiters par IP).');
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
