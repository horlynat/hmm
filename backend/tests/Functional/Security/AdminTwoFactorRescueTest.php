<?php

namespace App\Tests\Functional\Security;

use App\Tests\Functional\FunctionalTestCase;

/**
 * P1 #9 — un simple ROLE_ADMIN ne peut ni retirer la 2FA d'un super-admin
 * (même dormant), ni le déconnecter de force. Le refus a lieu avant même la
 * vérification CSRF : aucun jeton n'est nécessaire pour prouver l'attaque.
 */
final class AdminTwoFactorRescueTest extends FunctionalTestCase
{
    public function testRegularAdminCannotDisableSuperAdminTwoFactor(): void
    {
        $superAdmin = $this->createUser(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], twoFactor: true);
        $admin = $this->createUser(['ROLE_ADMIN'], twoFactor: true);

        $this->client->loginUser($admin, 'main');
        $this->client->request('POST', '/admin/security/2fa/'.$superAdmin->getId().'/disable', ['_token' => 'x']);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertTrue($this->reload($superAdmin)->isTotpAuthenticationEnabled());

        $this->client->request('POST', '/admin/security/sessions/user/'.$superAdmin->getId().'/revoke-all', ['_token' => 'x']);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testRegularAdminStillRescuesRegularAccount(): void
    {
        $client = $this->createUser(['ROLE_USER'], twoFactor: true);
        $admin = $this->createUser(['ROLE_ADMIN'], twoFactor: true);

        $this->client->loginUser($admin, 'main');
        $crawler = $this->client->request('GET', '/admin/security/2fa/');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $form = $crawler->filter(sprintf('form[action$="/admin/security/2fa/%d/disable"]', $client->getId()))->form();
        $this->client->submit($form);

        $fresh = $this->reload($client);
        self::assertFalse($fresh->isTotpAuthenticationEnabled());
        self::assertSame([], $fresh->getBackupCodes(), 'Les codes de récupération doivent disparaître avec la 2FA.');
    }
}
