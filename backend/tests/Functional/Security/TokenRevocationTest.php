<?php

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Service\JWTService;
use App\Tests\Functional\FunctionalTestCase;
use Doctrine\DBAL\Connection;

/**
 * P1 #8 — révocation : POST /api/logout (contrat 204), claim `tv`
 * (token_version), purge remember-me + sessions, TTL explicite ; P2 —
 * DELETE /api/me (hiérarchie + mot de passe actuel), limiteur sur la
 * re-saisie du mot de passe de PATCH /api/me.
 */
final class TokenRevocationTest extends FunctionalTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function payload(string $jwt): array
    {
        $data = json_decode(base64_decode(strtr(explode('.', $jwt)[1], '-_', '+/')), true);
        self::assertIsArray($data);

        return $data;
    }

    public function testTokenTtlIsExplicitAndShort(): void
    {
        $payload = $this->payload($this->jwtFor($this->createUser()));
        self::assertSame(1800, $payload['exp'] - $payload['iat']);
        self::assertArrayHasKey('jti', $payload);
        self::assertSame(0, $payload['tv']);
    }

    public function testLogoutRevokesOnlyThePresentedToken(): void
    {
        $user = $this->createUser(twoFactor: true);
        $jwt = $this->jwtFor($user);
        $otherDevice = $this->jwtFor($user);

        self::assertSame(200, $this->jsonRequest('GET', '/api/me', null, $jwt)->getStatusCode());

        $response = $this->jsonRequest('POST', '/api/logout', null, $jwt);
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', (string) $response->getContent());

        self::assertSame(401, $this->jsonRequest('GET', '/api/me', null, $jwt)->getStatusCode(), 'Jeton déconnecté réutilisé.');
        self::assertSame(401, $this->jsonRequest('POST', '/api/logout', null, $jwt)->getStatusCode(), 'Double déconnexion.');
        self::assertSame(200, $this->jsonRequest('GET', '/api/me', null, $otherDevice)->getStatusCode(), 'Les autres appareils restent connectés.');
    }

    public function testLogoutRequiresAValidJwt(): void
    {
        self::assertSame(401, $this->jsonRequest('POST', '/api/logout')->getStatusCode());
        self::assertSame(401, $this->jsonRequest('POST', '/api/logout', null, 'not.a.jwt')->getStatusCode());
        self::assertSame(405, $this->jsonRequest('GET', '/api/logout', null, $this->jwtFor($this->createUser()))->getStatusCode());
    }

    public function testForgedTokenVersionIsRejected(): void
    {
        $user = $this->createUser(twoFactor: true);
        $jwt = $this->jwtFor($user);
        [$h, $p, $sig] = explode('.', $jwt);
        $payload = $this->payload($jwt);
        $payload['tv'] = 99;
        $forged = $h.'.'.rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=').'.'.$sig;
        self::assertSame(401, $this->jsonRequest('GET', '/api/me', null, $forged)->getStatusCode());
    }

    public function testPasswordResetRevokesJwtSessionsAndRememberMe(): void
    {
        $user = $this->createUser(twoFactor: true);
        $jwt = $this->jwtFor($user);

        /** @var Connection $db */
        $db = static::getContainer()->get(Connection::class);
        $db->insert('rememberme_token', ['series' => bin2hex(random_bytes(20)), 'value' => 'x', 'lastUsed' => '2026-11-20 10:00:00', 'class' => User::class, 'username' => $user->getEmail()]);

        // Session web déjà ouverte sur un autre appareil.
        $this->client->loginUser($user, 'main');
        $this->client->request('GET', '/projects');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $now = new \DateTimeImmutable();
        $fresh = $this->reload($user);
        $fresh->setPasswordResetRequestedAt($now);
        $this->em()->flush();
        $resetToken = static::getContainer()->get(JWTService::class)->generatePasswordResetToken((int) $user->getId(), $now->getTimestamp());

        $response = $this->jsonRequest('POST', '/api/reset-password', ['token' => $resetToken, 'plainPassword' => 'N3w-Passw0rd!x']);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        self::assertSame(401, $this->jsonRequest('GET', '/api/me', null, $jwt)->getStatusCode(), 'Ancien JWT encore accepté.');
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM rememberme_token WHERE username = ?', [$user->getEmail()]));

        $this->client->request('GET', '/projects');
        self::assertTrue($this->client->getResponse()->isRedirect(), 'Ancienne session web encore valide.');
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));

        self::assertSame(200, $this->jsonRequest('GET', '/api/me', null, $this->jwtFor($this->reload($user)))->getStatusCode());
    }

    public function testApiPasswordChangeRevokesOldTokenAndReturnsANewOne(): void
    {
        $user = $this->createUser(twoFactor: true);
        $jwt = $this->jwtFor($user);
        $otherDevice = $this->jwtFor($user);

        $bad = $this->jsonRequest('PATCH', '/api/me', ['plainPassword' => 'N3w-Passw0rd!x', 'currentPassword' => 'wrong'], $jwt);
        self::assertSame(422, $bad->getStatusCode());

        $ok = $this->jsonRequest('PATCH', '/api/me', ['plainPassword' => 'N3w-Passw0rd!x', 'currentPassword' => self::PASSWORD], $jwt);
        self::assertSame(200, $ok->getStatusCode(), (string) $ok->getContent());
        $newToken = $this->json($ok)['token'] ?? null;
        self::assertIsString($newToken);

        self::assertSame(401, $this->jsonRequest('GET', '/api/me', null, $jwt)->getStatusCode());
        self::assertSame(401, $this->jsonRequest('GET', '/api/me', null, $otherDevice)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', '/api/me', null, $newToken)->getStatusCode());
    }

    public function testCurrentPasswordConfirmationIsThrottled(): void
    {
        $user = $this->createUser(twoFactor: true);
        $jwt = $this->jwtFor($user);

        for ($i = 0; $i < 5; ++$i) {
            self::assertSame(422, $this->jsonRequest('PATCH', '/api/me', ['plainPassword' => 'N3w-Passw0rd!x', 'currentPassword' => 'guess'.$i], $jwt)->getStatusCode());
        }
        // Même le BON mot de passe est refusé une fois le quota atteint : plus d'oracle.
        $response = $this->jsonRequest('PATCH', '/api/me', ['plainPassword' => 'N3w-Passw0rd!x', 'currentPassword' => self::PASSWORD], $jwt);
        self::assertSame(429, $response->getStatusCode());
        self::assertSame(429, $this->jsonRequest('DELETE', '/api/me', ['currentPassword' => self::PASSWORD], $jwt)->getStatusCode());
    }

    public function testAccountDeletionRequiresCurrentPasswordAndRevokes(): void
    {
        $user = $this->createUser(twoFactor: true);
        $jwt = $this->jwtFor($user);

        self::assertSame(422, $this->jsonRequest('DELETE', '/api/me', null, $jwt)->getStatusCode());
        self::assertSame(422, $this->jsonRequest('DELETE', '/api/me', ['currentPassword' => 'nope'], $jwt)->getStatusCode());
        self::assertTrue($this->reload($user)->isActive());

        self::assertSame(200, $this->jsonRequest('DELETE', '/api/me', ['currentPassword' => self::PASSWORD], $jwt)->getStatusCode());
        self::assertFalse($this->reload($user)->isActive());
        self::assertSame(401, $this->jsonRequest('GET', '/api/me', null, $jwt)->getStatusCode());
    }

    public function testStaffAccountsCannotSelfDeleteWhateverTheirRank(): void
    {
        foreach (['ROLE_EDITOR', 'ROLE_MODERATOR', 'ROLE_MANAGER'] as $role) {
            $staff = $this->createUser([$role], twoFactor: true);
            $response = $this->jsonRequest('DELETE', '/api/me', ['currentPassword' => self::PASSWORD], $this->jwtFor($staff));
            self::assertSame(403, $response->getStatusCode(), $role);
            self::assertTrue($this->reload($staff)->isActive(), $role);
        }
    }
}
