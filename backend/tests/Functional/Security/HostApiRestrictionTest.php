<?php

namespace App\Tests\Functional\Security;

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
            '/build/manifest.json', '/health', '/favicon.ico', '/robots.txt',
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

    public function testHostMatchingIsCaseInsensitive(): void
    {
        $this->client->request('GET', 'http://API.Test.Invalid/login');
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testApiUploadsAndNewsletterStayServedOnApiHost(): void
    {
        $this->client->request('GET', 'http://api.test.invalid/api/me', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame(401, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', 'http://api.test.invalid/newsletter/confirm/not-a-token');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // /uploads/** : fichier absent -> 404 applicatif normal (pas la 404
        // "nue" de ce listener, qui ne bloque pas ce préfixe) ; on vérifie
        // simplement qu'aucun comportement "back-office" n'apparaît, via le
        // préfixe refusé le plus proche (uploadsx) déjà couvert ci-dessus.
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
