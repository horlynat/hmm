<?php

namespace App\Tests\Functional\Security;

use App\Tests\Functional\FunctionalTestCase;

/**
 * P2 — élévation PAM portée par la session qui l'a demandée, jamais par les
 * JWT du compte ni par ses autres sessions.
 */
final class PrivilegeElevationScopeTest extends FunctionalTestCase
{
    public function testElevationStaysInItsOwnSession(): void
    {
        $superAdmin = $this->createUser(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], twoFactor: true);

        $this->client->loginUser($superAdmin, 'main');
        $this->client->request('GET', '/admin/security/permissions/');
        self::assertSame(403, $this->client->getResponse()->getStatusCode(), 'Dormant : pas encore super-admin.');

        $crawler = $this->client->request('GET', '/admin/profil/'.$superAdmin->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $form = $crawler->filter('form[action$="/profile/elevation/activate"]')->form(['password' => self::PASSWORD]);
        $this->client->submit($form);
        self::assertSame(302, $this->client->getResponse()->getStatusCode());

        // Même session : élevée, et toujours connectée (jeton reconstruit).
        $this->client->request('GET', '/admin/security/permissions/');
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'La session qui a demandé l\'élévation doit être super-admin.');

        // L'élévation est bien en base…
        self::assertNotNull($this->reload($superAdmin)->getSuperAdminElevatedUntil());

        // … mais un JWT du même compte ne l'obtient jamais.
        $jwt = $this->jwtFor($this->reload($superAdmin));
        $payload = json_decode(base64_decode(strtr(explode('.', $jwt)[1], '-_', '+/')), true);
        self::assertIsArray($payload);
        self::assertNotContains('ROLE_SUPER_ADMIN', $payload['roles'] ?? []);

        $response = $this->jsonRequest('GET', '/api/me', null, $jwt);
        self::assertSame(200, $response->getStatusCode());
        self::assertNotContains('ROLE_SUPER_ADMIN', $this->json($response)['roles'] ?? []);

        // Une AUTRE session du même compte non plus (nouveau cookie de
        // session, même élévation encore active en base).
        $this->client->getCookieJar()->clear();
        $this->client->loginUser($this->reload($superAdmin), 'main');
        $this->client->request('GET', '/admin/security/permissions/');
        self::assertSame(403, $this->client->getResponse()->getStatusCode(), 'Une autre session ne doit pas hériter de l\'élévation.');
    }

    public function testWrongPasswordDoesNotElevate(): void
    {
        $superAdmin = $this->createUser(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], twoFactor: true);
        $this->client->loginUser($superAdmin, 'main');

        $crawler = $this->client->request('GET', '/admin/profil/'.$superAdmin->getId());
        $form = $crawler->filter('form[action$="/profile/elevation/activate"]')->form(['password' => 'wrong-password']);
        $this->client->submit($form);

        $this->client->request('GET', '/admin/security/permissions/');
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertNull($this->reload($superAdmin)->getSuperAdminElevatedUntil());
    }
}
