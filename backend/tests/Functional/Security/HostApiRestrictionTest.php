<?php

namespace App\Tests\Functional\Security;

use App\EventSubscriber\PublicApiHostRestrictionSubscriber;
use App\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * P0 #4 (partie Symfony) : sur l'hôte api.*, seuls /api, /uploads et
 * /newsletter répondent — exactement les préfixes auxquels le routeur
 * Traefik `api` restreint déjà ce service côté infra (lot I1).
 *
 * .env.test : PUBLIC_API_HOSTS=api.test.invalid.
 */
final class HostApiRestrictionTest extends FunctionalTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function backOfficePathsProvider(): iterable
    {
        foreach ([
            '/login', '/login/oidc', '/logout', '/admin', '/admin/', '/admin/profil/1', '/2fa', '/2fa_check',
            '/profile/2fa', '/profile/elevation/activate', '/register', '/verif/abc', '/renvoiverif',
            '/mot-de-passe-oublie', '/reinitialiser-mot-de-passe/x', '/user/profil', '/projects', '/mes-devis',
            '/mes-sessions/1/revoke', '/_components/admin_project_table', '/', '/%61dmin', '//admin',
            '/api/../admin', '/apiadmin', '/API/me', '/uploadsx', '/newsletterx', '/admin?x=/api/',
            '/health',
        ] as $path) {
            yield $path => [$path];
        }
    }

    #[DataProvider('backOfficePathsProvider')]
    public function testOnlyApiUploadsAndNewsletterAreServedOnApiHost(string $path): void
    {
        $this->client->request('GET', 'http://api.test.invalid'.$path);
        self::assertSame(404, $this->client->getResponse()->getStatusCode(), $path);
        self::assertSame('Not Found', $this->client->getResponse()->getContent());

        $this->client->request('POST', 'http://api.test.invalid'.$path, ['email' => 'x@y.z', 'password' => 'x']);
        self::assertSame(404, $this->client->getResponse()->getStatusCode(), 'POST '.$path);
    }

    /**
     * Portée RÉELLE pour les chemins de fichiers statiques : ce test ne
     * prouve que le refus des requêtes qui ATTEIGNENT PHP (fichier absent).
     * En prod, un fichier existant de public/ (/build, /bundles, /uploads,
     * favicon…) est servi par Caddy avant PHP : sur l'hôte API, seul le
     * routeur Traefik le protège — pas ce listener.
     */
    public function testMissingStaticPathsReachingPhpAreRefused(): void
    {
        foreach (['/build/manifest.json', '/bundles/x.js', '/favicon.ico', '/robots.txt'] as $path) {
            $this->client->request('GET', 'http://api.test.invalid'.$path);
            self::assertSame(404, $this->client->getResponse()->getStatusCode(), $path);
        }
    }

    public function testHostMatchingIsCaseInsensitive(): void
    {
        $this->client->request('GET', 'http://API.Test.Invalid/login');
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Contre-audit : `api.example.` (point final, FQDN absolu) est le même
     * hôte pour le DNS/Traefik ; il ne doit pas échapper à la restriction.
     */
    public function testTrailingDotInHostDoesNotBypassRestriction(): void
    {
        // (`api.test.invalid..` n'est même pas un Host valide : Symfony le
        // rejette en amont — 400 en production, exception dans le client de test.)
        foreach (['http://api.test.invalid./login', 'http://API.TEST.INVALID./admin', 'http://api.test.invalid./2fa'] as $url) {
            $this->client->request('GET', $url);
            self::assertSame(404, $this->client->getResponse()->getStatusCode(), $url);
        }

        $this->client->request('GET', 'http://api.test.invalid./api/me', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testConfiguredHostListIsNormalized(): void
    {
        self::assertSame(
            ['api.horlynat.com', 'other.example'],
            PublicApiHostRestrictionSubscriber::parseHostList(' API.Horlynat.com. , other.example.., ,'),
        );
    }

    public function testApiUploadsAndNewsletterStayServedOnApiHost(): void
    {
        $this->client->request('GET', 'http://api.test.invalid/api/me', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame(401, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', 'http://api.test.invalid/newsletter/confirm/not-a-token');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // /uploads/** : le listener laisse passer (fichier absent -> 404 du
        // routeur, pas la 404 « nue » du listener). En prod, un fichier
        // existant y est servi par Caddy sans PHP.
        $this->client->request('GET', 'http://api.test.invalid/uploads/anything.png');
        self::assertNotSame('Not Found', $this->client->getResponse()->getContent());
    }

    public function testOtherHostsKeepServingBackOffice(): void
    {
        $this->client->request('GET', 'http://localhost/login');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testInactiveWhenNoHostsConfigured(): void
    {
        self::assertSame([], \App\EventSubscriber\PublicApiHostRestrictionSubscriber::parseHostList(null));
        self::assertSame([], \App\EventSubscriber\PublicApiHostRestrictionSubscriber::parseHostList(''));
        self::assertSame([], \App\EventSubscriber\PublicApiHostRestrictionSubscriber::parseHostList('   '));
    }
}
