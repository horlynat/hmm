<?php

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Tests\Functional\FunctionalTestCase;
use Doctrine\DBAL\Connection;

/**
 * Contre-audit B2 (M2, 2e passe) : la même règle que pour le titulaire du
 * compte s'applique quand c'est un administrateur qui change l'e-mail d'un
 * compte ou le supprime depuis le back-office — aucun jeton émis avant ne
 * doit survivre, et l'ancienne adresse ne doit plus rien authentifier.
 */
final class AdminIdentityRevocationTest extends FunctionalTestCase
{
    public function testAdminEmailChangeRevokesJwtAndPurgesRememberMeOfTheOldAddress(): void
    {
        $client = $this->createUser(twoFactor: true);
        $old = $client->getEmail();
        $jwt = $this->jwtFor($client);
        $this->insertRememberMe($old);
        self::assertSame(200, $this->jsonRequest('GET', '/api/me', null, $jwt)->getStatusCode());

        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
        $crawler = $this->client->request('GET', '/admin/users/'.$client->getId().'/edit');
        self::assertResponseIsSuccessful();
        $new = 'moved-'.bin2hex(random_bytes(4)).'@test.invalid';
        $form = $crawler->filter('input[name="user[email]"]')->closest('form')->form(['user[email]' => $new]);
        $this->client->submit($form);
        self::assertResponseRedirects();
        self::assertSame($new, $this->reload($client)->getEmail());

        self::assertSame(0, $this->rememberMeCount($old), "remember-me de l'ancienne adresse encore présents");
        self::assertSame(401, $this->jsonRequest('GET', '/api/me', null, $jwt)->getStatusCode(), 'JWT émis avant le changement encore valide');
    }

    public function testAdminEditWithoutEmailChangeKeepsTheClientSessions(): void
    {
        $client = $this->createUser(twoFactor: true);
        $jwt = $this->jwtFor($client);

        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
        $crawler = $this->client->request('GET', '/admin/users/'.$client->getId().'/edit');
        $form = $crawler->filter('input[name="user[email]"]')->closest('form')->form();
        $this->client->submit($form);
        self::assertResponseRedirects();

        self::assertSame(200, $this->jsonRequest('GET', '/api/me', null, $jwt)->getStatusCode(), 'Une édition sans changement d\'e-mail ne doit déconnecter personne.');
    }

    public function testAdminDeletionPurgesRememberMe(): void
    {
        $client = $this->createUser(twoFactor: true);
        $email = $client->getEmail();
        $id = $client->getId();
        $this->insertRememberMe($email);

        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
        $crawler = $this->client->request('GET', '/admin/users/'.$id);
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('form[action$="/admin/users/'.$id.'"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/admin/users/'.$id, ['_token' => $token]);
        self::assertResponseRedirects();

        $this->em()->clear();
        self::assertNull($this->em()->find(User::class, $id), 'compte non supprimé');
        self::assertSame(0, $this->rememberMeCount($email), 'remember-me du compte supprimé encore présents');
    }

    private function db(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    private function insertRememberMe(string $email): void
    {
        $this->db()->insert('rememberme_token', [
            'series' => bin2hex(random_bytes(20)),
            'value' => 'x',
            'lastUsed' => (new \DateTime())->format('Y-m-d H:i:s'),
            'class' => User::class,
            'username' => $email,
        ]);
    }

    private function rememberMeCount(string $email): int
    {
        return (int) $this->db()->fetchOne('SELECT COUNT(*) FROM rememberme_token WHERE username = ?', [$email]);
    }
}
