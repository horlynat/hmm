<?php

namespace App\Tests\Functional\Security;

use App\Entity\UserSession;
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

    /**
     * Contre-audit B2 : défense contre la fixation de session — l'identifiant
     * qui existait avant l'élévation ne doit plus être celui de la session
     * super-admin, et le suivi (« mes sessions », révocation) doit suivre.
     */
    public function testElevationRotatesTheSessionIdAndKeepsItsTracking(): void
    {
        $superAdmin = $this->createUser(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], twoFactor: true);
        $this->client->loginUser($superAdmin, 'main');
        $crawler = $this->client->request('GET', '/admin/profil/'.$superAdmin->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $before = $this->client->getRequest()->getSession()->getId();
        self::assertNotSame('', $before);
        $tracked = new UserSession($superAdmin, $before, '10.0.0.1', 'phpunit');
        $this->em()->persist($tracked);
        $this->em()->flush();

        $this->client->submit($crawler->filter('form[action$="/profile/elevation/activate"]')->form(['password' => self::PASSWORD]));
        self::assertSame(302, $this->client->getResponse()->getStatusCode());

        $after = $this->sessionCookieSetBy($this->client->getResponse());
        self::assertNotNull($after, "La réponse d'élévation doit poser le nouveau cookie de session.");
        self::assertNotSame($before, $after, "L'identifiant de session doit changer à l'élévation.");

        $trackedId = $tracked->getId();
        $this->em()->clear();
        $reloaded = $this->em()->find(UserSession::class, $trackedId);
        self::assertNotNull($reloaded, 'Le suivi de session ne doit pas être perdu.');
        self::assertSame($after, $reloaded->getSessionId(), 'Le suivi de session doit suivre le nouvel identifiant.');

        $this->client->request('GET', '/admin/security/permissions/');
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'La session renouvelée reste super-admin.');
    }

    private function sessionCookieSetBy(\Symfony\Component\HttpFoundation\Response $response): ?string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if (str_contains(strtoupper($cookie->getName()), 'SESS') && '' !== (string) $cookie->getValue()) {
                return (string) $cookie->getValue();
            }
        }

        return null;
    }
}
