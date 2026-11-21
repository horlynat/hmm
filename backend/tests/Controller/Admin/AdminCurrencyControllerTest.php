<?php

namespace App\Tests\Controller\Admin;

use App\Tests\Functional\FunctionalTestCase;

/**
 * AdminCurrencyController::set() (audit 2026-11-20, Info) : CSRF + open
 * redirect chaîné via le Referer réinjecté tel quel.
 *
 * Admin AVEC 2FA (FunctionalTestCase::createUser) : depuis le lot B2, un
 * admin sans 2FA est redirigé vers sa mise en place avant tout écran du
 * back-office — sans 2FA, ces tests vérifiaient la redirection 2FA et non le
 * CSRF ni l'anti open-redirect.
 */
final class AdminCurrencyControllerTest extends FunctionalTestCase
{
    private function dashboardToken(): string
    {
        $crawler = $this->client->request('GET', '/admin/dashboard/');
        self::assertResponseIsSuccessful('Le tableau de bord doit s’afficher pour un admin équipé de la 2FA.');

        $token = (string) $crawler->filter('form[action$="/admin/currency/set"] input[name="_token"]')->attr('value');
        self::assertNotSame('', $token);

        return $token;
    }

    public function testRequestWithoutCsrfTokenDoesNotSetTheCookie(): void
    {
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
        // Le tableau de bord s'affiche bien : la redirection observée ensuite
        // vient du refus CSRF, pas de la 2FA obligatoire.
        $this->dashboardToken();

        $this->client->request('POST', '/admin/currency/set', ['currency' => 'USD'], server: [
            'HTTP_REFERER' => 'https://dark.horlynat.com/admin/dashboard',
        ]);

        self::assertResponseRedirects();
        self::assertNull($this->client->getResponse()->headers->getCookies()[0] ?? null, 'Sans jeton CSRF valide, aucun cookie ne doit être posé.');
    }

    public function testForeignRefererIsNeverFollowed(): void
    {
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
        $token = $this->dashboardToken();

        // Un attaquant forge le Referer : tente de rediriger l'admin vers un site tiers (phishing).
        $this->client->request('POST', '/admin/currency/set', [
            '_token' => $token,
            'currency' => 'USD',
        ], server: [
            'HTTP_REFERER' => 'https://evil.example/phishing',
        ]);

        self::assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        self::assertNotNull($location);
        self::assertStringNotContainsString('evil.example', $location, 'Le Referer forgé ne doit jamais devenir la cible de redirection.');
    }

    public function testValidRequestWithSameHostRefererSetsCookieAndRedirectsBack(): void
    {
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
        $token = $this->dashboardToken();

        $this->client->request('POST', '/admin/currency/set', [
            '_token' => $token,
            'currency' => 'USD',
        ], server: [
            'HTTP_REFERER' => 'http://localhost/admin/dashboard',
        ]);

        self::assertResponseRedirects('http://localhost/admin/dashboard');
        $cookies = $this->client->getResponse()->headers->getCookies();
        self::assertNotEmpty($cookies);
        self::assertSame('display_currency', $cookies[0]->getName());
        self::assertSame('USD', $cookies[0]->getValue());
    }
}
