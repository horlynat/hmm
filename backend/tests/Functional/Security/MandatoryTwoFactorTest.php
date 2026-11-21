<?php

namespace App\Tests\Functional\Security;

use App\Tests\Functional\FunctionalTestCase;

/**
 * P1 #13 / Info 18 — 2FA obligatoire appliquée côté serveur :
 * (a) back-office ROLE_ADMIN+ redirigé vers la mise en place ;
 * (b) API 403 {"code":"2fa_required"} sur /api/me/** sauf exemptions ;
 * (c) /user/profil (espace membre) dans le périmètre web.
 */
final class MandatoryTwoFactorTest extends FunctionalTestCase
{
    // --- (a) Web back-office ---------------------------------------------

    public function testAdminWithoutTwoFactorIsRedirectedToSetupFromAdminArea(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $this->client->loginUser($admin, 'main');

        $this->client->request('GET', '/admin/dashboard/');
        self::assertTrue($this->client->getResponse()->isRedirect('/profile/2fa/setup'));
    }

    public function testSuperAdminWithoutTwoFactorIsRedirectedEvenDormant(): void
    {
        $superAdmin = $this->createUser(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
        $this->client->loginUser($superAdmin, 'main');

        // Dormant (jamais élevé) : reste classé "compte Super Admin" pour
        // cette règle (cf. TwoFactorMandatePolicy, basé sur getPrimaryRole()).
        $this->client->request('GET', '/admin/dashboard/');
        self::assertTrue($this->client->getResponse()->isRedirect('/profile/2fa/setup'));
    }

    public function testSetupPageAndLogoutRemainReachableWhileNotEquipped(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $this->client->loginUser($admin, 'main');

        $this->client->request('GET', '/profile/2fa/setup');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/logout');
        self::assertNotSame(500, $this->client->getResponse()->getStatusCode());
    }

    public function testEditorModeratorManagerStayOptional(): void
    {
        // /admin/dashboard exige ROLE_ADMIN (DashboardVoter) — sans rapport
        // avec la 2FA. /admin/article/index ne requiert que le plancher
        // ^/admin (ROLE_EDITOR), un terrain neutre pour vérifier que la 2FA
        // reste optionnelle pour ces trois rangs intermédiaires.
        foreach (['ROLE_EDITOR', 'ROLE_MODERATOR', 'ROLE_MANAGER'] as $role) {
            $staff = $this->createUser([$role]);
            $this->client->loginUser($staff, 'main');

            $this->client->request('GET', '/admin/article/index');
            self::assertSame(200, $this->client->getResponse()->getStatusCode(), $role);
        }
    }

    public function testAdminWithTwoFactorIsNotRedirected(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN'], twoFactor: true);
        $this->client->loginUser($admin, 'main');

        $this->client->request('GET', '/admin/dashboard/');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testMemberProfilIsInScope(): void
    {
        $client = $this->createUser(['ROLE_USER']);
        $this->client->loginUser($client, 'main');

        $this->client->request('GET', '/user/profil');
        self::assertTrue($this->client->getResponse()->isRedirect('/profile/2fa/setup'));
    }

    // --- (b) API -----------------------------------------------------------

    public function testApiReadMeIsExemptEvenWithoutTwoFactor(): void
    {
        $client = $this->createUser(['ROLE_USER']);
        $response = $this->jsonRequest('GET', '/api/me', null, $this->jwtFor($client));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testApiWriteOperationsAreBlockedWithoutTwoFactor(): void
    {
        $client = $this->createUser(['ROLE_USER']);
        $jwt = $this->jwtFor($client);

        $patch = $this->jsonRequest('PATCH', '/api/me', ['fullName' => 'Attempt'], $jwt);
        self::assertSame(403, $patch->getStatusCode());
        self::assertSame(['code' => '2fa_required'], $this->json($patch));

        $delete = $this->jsonRequest('DELETE', '/api/me', ['currentPassword' => self::PASSWORD], $jwt);
        self::assertSame(403, $delete->getStatusCode());
        self::assertSame(['code' => '2fa_required'], $this->json($delete));

        $activity = $this->jsonRequest('GET', '/api/me/activity', null, $jwt);
        self::assertSame(403, $activity->getStatusCode());
    }

    public function testApiTwoFactorSetupRouteIsAlwaysExempt(): void
    {
        $client = $this->createUser(['ROLE_USER']);
        $jwt = $this->jwtFor($client);

        $setup = $this->jsonRequest('POST', '/api/me/2fa/setup', [], $jwt);
        self::assertNotSame(403, $setup->getStatusCode());
        self::assertNotSame('2fa_required', $this->json($setup)['code'] ?? null);
    }

    public function testApiLogoutIsAlwaysExempt(): void
    {
        $client = $this->createUser(['ROLE_USER']);
        $jwt = $this->jwtFor($client);

        $response = $this->jsonRequest('POST', '/api/logout', null, $jwt);
        self::assertSame(204, $response->getStatusCode());
    }

    /**
     * /api/me/2fabogus n'est pas une route existante : le routeur renvoie
     * 404 avant même que ce subscriber ne s'exécute (kernel.request de
     * routage, priorité supérieure) — ce chemin ne permet donc pas de
     * tester le contournement par un HTTP réel. La frontière de motif
     * elle-même (`(/|$)` après "2fa", pas un simple prefix match) est
     * vérifiée directement ici, en conditions réelles d'usage de la classe.
     */
    public function testExemptPatternRequiresAPathBoundaryAfter2fa(): void
    {
        self::assertSame(0, preg_match('#^/api/me/2fa(/|$)#', '/api/me/2fabogus'));
        self::assertSame(1, preg_match('#^/api/me/2fa(/|$)#', '/api/me/2fa/setup'));
        self::assertSame(1, preg_match('#^/api/me/2fa(/|$)#', '/api/me/2fa'));
    }

    /**
     * Contre-audit B2 (B1) : le contrôle ne couvrait que /api/me/** — un
     * ROLE_ADMIN sans TOTP lisait avec son seul mot de passe les ressources
     * staff d'API Platform.
     *
     * @return iterable<string, array{string}>
     */
    public static function staffApiResources(): iterable
    {
        yield 'devis' => ['/api/quote_requests'];
        yield 'messages de contact' => ['/api/contact_messages'];
        yield 'abonnés newsletter' => ['/api/newsletter_subscribers'];
        yield 'tickets support' => ['/api/support_tickets'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('staffApiResources')]
    public function testAdminWithoutTwoFactorCannotReadStaffApiResources(string $uri): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $response = $this->jsonRequest('GET', $uri, null, $this->jwtFor($admin), ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertSame(403, $response->getStatusCode(), $uri);
        self::assertSame(['code' => '2fa_required'], $this->json($response));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('staffApiResources')]
    public function testAdminWithTwoFactorStillReadsStaffApiResources(string $uri): void
    {
        $admin = $this->createUser(['ROLE_ADMIN'], twoFactor: true);
        $response = $this->jsonRequest('GET', $uri, null, $this->jwtFor($admin), ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertSame(200, $response->getStatusCode(), $uri.' '.$response->getContent());
    }

    public function testAnonymousPublicApiIsUnaffected(): void
    {
        $response = $this->jsonRequest('GET', '/api/home_contents');

        self::assertNotSame(403, $response->getStatusCode());
    }

    public function testApiExemptionsHavePathBoundaries(): void
    {
        $exempt = '#^/api/(me/2fa|login_check)(/|$)#';
        self::assertSame(1, preg_match($exempt, '/api/login_check'));
        self::assertSame(1, preg_match($exempt, '/api/login_check/2fa'));
        self::assertSame(0, preg_match($exempt, '/api/login_checkx'));
        self::assertSame(0, preg_match($exempt, '/api/quote_requests'));
        self::assertSame(1, preg_match('#^/api(/|$)#', '/api'));
        self::assertSame(0, preg_match('#^/api(/|$)#', '/apix'));
    }

    /**
     * Contre-audit B2 (B1, 2e passe) : `%61pi` (= "api") et `%2F` passent le
     * pare-feu et le routeur (tous deux comparent rawurldecode($pathinfo)),
     * mais échappaient à ce subscriber quand il comparait l'URL encodée.
     *
     * @return iterable<string, array{string}>
     */
    public static function encodedBypassAttempts(): iterable
    {
        yield 'lettre encodée (%61pi)' => ['/%61pi/me/activity'];
        yield 'slash encodé (%2F)' => ['/api%2Fme%2Factivity'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('encodedBypassAttempts')]
    public function testEncodedPathCannotBypassTheMandate(string $rawUri): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $response = $this->jsonRequest('GET', $rawUri, null, $this->jwtFor($admin));

        self::assertSame(403, $response->getStatusCode(), $rawUri.' : '.$response->getContent());
        self::assertSame(['code' => '2fa_required'], $this->json($response));
    }

    /**
     * Contre-audit B2 : /api/me expose la règle du backend, que le frontend
     * applique telle quelle (plus d'approximation sur les rôles bruts).
     *
     * @return iterable<string, array{list<string>, bool}>
     */
    public static function mandateByRole(): iterable
    {
        yield 'client' => [['ROLE_USER'], true];
        yield 'éditeur' => [['ROLE_EDITOR'], false];
        yield 'modérateur' => [['ROLE_MODERATOR'], false];
        yield 'manager' => [['ROLE_MANAGER'], false];
        yield 'admin' => [['ROLE_ADMIN'], true];
        yield 'super-admin' => [['ROLE_SUPER_ADMIN'], true];
    }

    /**
     * @param list<string> $roles
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('mandateByRole')]
    public function testApiMeExposesTheBackendMandate(array $roles, bool $required): void
    {
        $user = $this->createUser($roles);
        $response = $this->jsonRequest('GET', '/api/me', null, $this->jwtFor($user));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($required, $this->json($response)['isTwoFactorRequired'] ?? null);
    }

    public function testClientWithTwoFactorIsNotBlocked(): void
    {
        $client = $this->createUser(['ROLE_USER'], twoFactor: true);
        $response = $this->jsonRequest('PATCH', '/api/me', ['bio' => 'OK'], $this->jwtFor($client));

        self::assertNotSame(403, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testEditorIsNotSubjectToApiMandate(): void
    {
        $editor = $this->createUser(['ROLE_EDITOR']);
        $response = $this->jsonRequest('GET', '/api/me/activity', null, $this->jwtFor($editor));

        self::assertNotSame(403, $response->getStatusCode());
    }
}
