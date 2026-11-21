<?php

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Service\JWTService;
use App\Tests\Functional\FunctionalTestCase;
use Doctrine\DBAL\Connection;

/**
 * Contre-audit B2 (M2) : un jeton (JWT ou remember-me) émis pour un compte
 * ne doit jamais authentifier un AUTRE compte qui reprend ensuite la même
 * adresse e-mail (changement d'e-mail, ou suppression puis réinscription).
 */
final class IdentityReuseRevocationTest extends FunctionalTestCase
{
    public function testEmailChangePurgesRememberMeOfTheOldAddress(): void
    {
        $user = $this->createUser(twoFactor: true);
        $old = $user->getEmail();
        $db = $this->db();
        $db->insert('rememberme_token', [
            'series' => bin2hex(random_bytes(20)),
            'value' => 'x',
            'lastUsed' => (new \DateTime())->format('Y-m-d H:i:s'),
            'class' => User::class,
            'username' => $old,
        ]);

        $this->confirmEmailChange($user, 'n-'.bin2hex(random_bytes(4)).'@test.invalid');

        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM rememberme_token WHERE username = ?', [$old]));
    }

    public function testOldJwtNeverAuthenticatesANewAccountReusingTheAddressAfterEmailChange(): void
    {
        $a = $this->createUser(twoFactor: true);
        $old = $a->getEmail();
        $jwtA = $this->jwtFor($a);

        $this->confirmEmailChange($a, 'moved-'.bin2hex(random_bytes(4)).'@test.invalid');
        self::assertSame(401, $this->jsonRequest('GET', '/api/me', null, $jwtA)->getStatusCode());

        $this->createUser(twoFactor: true, email: $old);
        self::assertSame(401, $this->jsonRequest('GET', '/api/me', null, $jwtA)->getStatusCode());
    }

    public function testStolenJwtNeverAuthenticatesANewAccountAfterDeletionAndReRegistration(): void
    {
        $a = $this->createUser(twoFactor: true);
        $old = $a->getEmail();
        $stolen = $this->jwtFor($a);

        self::assertSame(200, $this->jsonRequest('DELETE', '/api/me', ['currentPassword' => self::PASSWORD], $this->jwtFor($a))->getStatusCode());

        $this->createUser(twoFactor: true, email: $old);
        self::assertSame(401, $this->jsonRequest('GET', '/api/me', null, $stolen)->getStatusCode());
    }

    public function testFreshJwtCarriesTheAccountIdAndStillWorks(): void
    {
        $user = $this->createUser(twoFactor: true);
        $jwt = $this->jwtFor($user);
        $payload = json_decode(base64_decode(strtr(explode('.', $jwt)[1], '-_', '+/')), true);

        self::assertIsArray($payload);
        self::assertSame($user->getId(), $payload['uid'] ?? null);
        self::assertSame(200, $this->jsonRequest('GET', '/api/me', null, $jwt)->getStatusCode());
    }

    private function db(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    private function confirmEmailChange(User $user, string $newEmail): void
    {
        $now = new \DateTimeImmutable();
        $fresh = $this->reload($user);
        $fresh->requestEmailChange($newEmail, $now);
        $this->em()->flush();
        $token = static::getContainer()->get(JWTService::class)->generateEmailChangeToken((int) $user->getId(), $now->getTimestamp());
        $this->client->request('GET', '/profile/email/confirmer/'.$token);
        self::assertTrue($this->client->getResponse()->isRedirect());
        self::assertSame($newEmail, $this->reload($user)->getEmail());
    }
}
