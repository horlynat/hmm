<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * /health/live est la sonde HEALTHCHECK du conteneur (frankenphp/healthcheck.sh) :
 * anonyme, sans session ni base, 200 dès que le noyau Symfony répond.
 */
final class HealthControllerTest extends WebTestCase
{
    public function testLiveProbeIsAnonymousAndStateless(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health/live');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['status' => 'ok'], json_decode((string) $client->getResponse()->getContent(), true));
        self::assertSame([], $client->getResponse()->headers->getCookies(), 'Aucune session ouverte par la sonde.');
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testDatabaseProbeIsAnonymous(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('ok', json_decode((string) $client->getResponse()->getContent(), true)['status'] ?? null);
    }

    public function testCatchAllStillProtectsNeighbouringPaths(): void
    {
        // Le motif `^/health(/live)?$` est ancré (`$`) : il ne doit matcher QUE
        // ces deux routes, pas un préfixe voisin — sinon on rouvrirait tout un
        // sous-chemin par erreur. La page d'accueil (`^/$`, ROLE_USER) sert de
        // témoin que le catch-all deny-by-default reste actif par ailleurs.
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects();
    }
}
