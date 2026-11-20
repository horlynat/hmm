<?php

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Service\JWTService;
use App\Tests\Functional\FunctionalTestCase;
use Doctrine\DBAL\Connection;

/**
 * P1 #7 — ré-authentification pour changer mot de passe/e-mail (profils web
 * membre + admin) et changement d'e-mail en deux temps.
 *
 * Scénario d'attaque reproduit : session volée (cookie dérobé, XSS, poste
 * partagé...) tentant de verrouiller le titulaire légitime hors de son
 * compte en changeant son mot de passe ou son e-mail SANS connaître le mot
 * de passe actuel.
 */
final class ProfileReauthTest extends FunctionalTestCase
{
    // ----- Mot de passe : page "Modifier mon profil" (/user/profil/modifier) -----

    public function testStolenSessionCannotChangePasswordViaUpdateFormWithoutCurrentPassword(): void
    {
        $user = $this->createUser(['ROLE_USER'], twoFactor: true);
        $this->client->loginUser($user, 'main');

        $crawler = $this->client->request('GET', '/user/profil/modifier');
        $form = $crawler->selectButton('Enregistrer les modifications')->form([
            'profile[plainPassword]' => 'New-Hijacked-Pwd1!',
            'profile[confirmPassword]' => 'New-Hijacked-Pwd1!',
        ]);
        $this->client->submit($form);

        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'La page doit se rerendre avec une erreur, pas rediriger.');
        self::assertStringContainsString('Mot de passe actuel incorrect', (string) $this->client->getResponse()->getContent());

        $fresh = $this->reload($user);
        self::assertTrue($this->passwordHasher()->isPasswordValid($fresh, self::PASSWORD), 'Le mot de passe ne doit pas avoir changé.');
    }

    public function testCorrectCurrentPasswordAllowsPasswordChangeViaUpdateForm(): void
    {
        $user = $this->createUser(['ROLE_USER'], twoFactor: true);
        $this->client->loginUser($user, 'main');

        $crawler = $this->client->request('GET', '/user/profil/modifier');
        $form = $crawler->selectButton('Enregistrer les modifications')->form([
            'profile[currentPassword]' => self::PASSWORD,
            'profile[plainPassword]' => 'New-Legit-Pwd1!',
            'profile[confirmPassword]' => 'New-Legit-Pwd1!',
        ]);
        $this->client->submit($form);

        self::assertTrue($this->client->getResponse()->isRedirect('/user/profil'));
        self::assertTrue($this->passwordHasher()->isPasswordValid($this->reload($user), 'New-Legit-Pwd1!'));
    }

    public function testStolenSessionCannotChangePasswordViaDedicatedPage(): void
    {
        $user = $this->createUser(['ROLE_USER'], twoFactor: true);
        $this->client->loginUser($user, 'main');

        $crawler = $this->client->request('GET', '/user/profil/mot-de-passe');
        $form = $crawler->selectButton('Changer le mot de passe')->form([
            'change_password_form[currentPassword]' => 'totally-wrong',
            'change_password_form[plainPassword]' => 'New-Hijacked-Pwd2!',
            'change_password_form[confirmPassword]' => 'New-Hijacked-Pwd2!',
        ]);
        $this->client->submit($form);

        self::assertStringContainsString('Mot de passe actuel incorrect', (string) $this->client->getResponse()->getContent());
        self::assertTrue($this->passwordHasher()->isPasswordValid($this->reload($user), self::PASSWORD));
    }

    public function testPasswordChangeRevokesOtherSessionsAndTokens(): void
    {
        $user = $this->createUser(['ROLE_USER'], twoFactor: true);
        $jwt = $this->jwtFor($user);
        self::assertSame(200, $this->jsonRequest('GET', '/api/me', null, $jwt)->getStatusCode());

        $this->client->loginUser($user, 'main');
        $crawler = $this->client->request('GET', '/user/profil/mot-de-passe');
        $form = $crawler->selectButton('Changer le mot de passe')->form([
            'change_password_form[currentPassword]' => self::PASSWORD,
            'change_password_form[plainPassword]' => 'New-Legit-Pwd3!',
            'change_password_form[confirmPassword]' => 'New-Legit-Pwd3!',
        ]);
        $this->client->submit($form);
        self::assertTrue($this->client->getResponse()->isRedirect());

        self::assertSame(401, $this->jsonRequest('GET', '/api/me', null, $jwt)->getStatusCode(), 'Le JWT émis avant le changement doit être révoqué.');

        // La session web qui vient de changer le mot de passe reste connectée.
        $this->client->request('GET', '/user/profil');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    // ----- E-mail : non éditable directement ------------------------------

    public function testProfileFormHasNoDirectEmailField(): void
    {
        $user = $this->createUser(['ROLE_USER'], twoFactor: true);
        $this->client->loginUser($user, 'main');

        $crawler = $this->client->request('GET', '/user/profil/modifier');
        self::assertCount(0, $crawler->filter('input[name="profile[email]"]'), "Le formulaire ne doit plus exposer l'e-mail en édition directe.");
    }

    public function testForgingAnEmailFieldOnTheUpdateFormHasNoEffect(): void
    {
        $user = $this->createUser(['ROLE_USER'], twoFactor: true);
        $originalEmail = $user->getEmail();
        $this->client->loginUser($user, 'main');

        $crawler = $this->client->request('GET', '/user/profil/modifier');
        $form = $crawler->selectButton('Enregistrer les modifications')->form();
        // Un attaquant qui rejoue la requête avec un champ "profile[email]"
        // ajouté à la main (DevTools, requête forgée) — le FormType n'a pas
        // ce champ mappé, Symfony l'ignore.
        $this->client->request($form->getMethod(), $form->getUri(), array_replace_recursive($form->getPhpValues(), ['profile' => ['email' => 'evil@attacker.test']]));

        self::assertSame($originalEmail, $this->reload($user)->getEmail());
    }

    // ----- Changement d'e-mail en deux temps -------------------------------

    public function testEmailChangeRequestRequiresCurrentPassword(): void
    {
        $user = $this->createUser(['ROLE_USER'], twoFactor: true);
        $this->client->loginUser($user, 'main');

        $crawler = $this->client->request('GET', '/profile/email');
        $form = $crawler->selectButton('Envoyer le lien de confirmation')->form([
            'email_change_request[newEmail]' => 'new-address@test.invalid',
            'email_change_request[currentPassword]' => 'wrong',
        ]);
        $this->client->submit($form);

        self::assertStringContainsString('Mot de passe actuel incorrect', (string) $this->client->getResponse()->getContent());
        self::assertNull($this->reload($user)->getPendingEmail());
    }

    public function testEmailChangeDoesNotApplyImmediately(): void
    {
        $user = $this->createUser(['ROLE_USER'], twoFactor: true);
        $originalEmail = $user->getEmail();
        $this->client->loginUser($user, 'main');

        $crawler = $this->client->request('GET', '/profile/email');
        $form = $crawler->selectButton('Envoyer le lien de confirmation')->form([
            'email_change_request[newEmail]' => 'new-address@test.invalid',
            'email_change_request[currentPassword]' => self::PASSWORD,
        ]);
        $this->client->submit($form);

        $fresh = $this->reload($user);
        self::assertSame($originalEmail, $fresh->getEmail(), "L'adresse active ne doit pas changer avant confirmation.");
        self::assertSame('new-address@test.invalid', $fresh->getPendingEmail());
        self::assertNotNull($fresh->getEmailChangeRequestedAt());

        $emails = implode("\n", $this->capturedEmails());
        self::assertStringContainsString('profile/email/confirmer/', $emails, "Un lien de confirmation doit être envoyé.");
        self::assertStringContainsString($originalEmail, $emails, "L'ancienne adresse doit recevoir une notification.");
    }

    public function testEmailChangeConfirmationCompletesTheChangeAndRevokesSessions(): void
    {
        $user = $this->createUser(['ROLE_USER'], twoFactor: true);
        $jwt = $this->jwtFor($user);

        $now = new \DateTimeImmutable();
        $fresh = $this->reload($user);
        $newEmail = sprintf('confirmed-%s@test.invalid', bin2hex(random_bytes(4)));
        $fresh->requestEmailChange($newEmail, $now);
        $this->em()->flush();

        $token = static::getContainer()->get(JWTService::class)->generateEmailChangeToken((int) $user->getId(), $now->getTimestamp());

        // Cliqué SANS être connecté sur ce navigateur (client mail distinct).
        $this->client->request('GET', '/profile/email/confirmer/'.$token);
        self::assertTrue($this->client->getResponse()->isRedirect('/login'));

        $fresh = $this->reload($user);
        self::assertSame($newEmail, $fresh->getEmail());
        self::assertNull($fresh->getPendingEmail());
        self::assertNull($fresh->getEmailChangeRequestedAt());

        self::assertSame(401, $this->jsonRequest('GET', '/api/me', null, $jwt)->getStatusCode(), 'Changer l\'e-mail doit révoquer les JWT existants.');
    }

    public function testEmailChangeConfirmationRejectsASupersededToken(): void
    {
        $user = $this->createUser(['ROLE_USER'], twoFactor: true);

        $firstRequestedAt = new \DateTimeImmutable('-10 minutes');
        $fresh = $this->reload($user);
        $fresh->requestEmailChange('first-address@test.invalid', $firstRequestedAt);
        $this->em()->flush();
        $staleToken = static::getContainer()->get(JWTService::class)->generateEmailChangeToken((int) $user->getId(), $firstRequestedAt->getTimestamp());

        // Une seconde demande, plus récente, supplante la première.
        $fresh = $this->reload($user);
        $fresh->requestEmailChange('second-address@test.invalid', new \DateTimeImmutable());
        $this->em()->flush();

        $this->client->request('GET', '/profile/email/confirmer/'.$staleToken);
        self::assertTrue($this->client->getResponse()->isRedirect('/login'));

        self::assertSame('second-address@test.invalid', $this->reload($user)->getPendingEmail(), "La demande la plus récente doit survivre à un vieux jeton rejoué.");
    }

    public function testEmailChangeConfirmationRejectsAForgedTokenForAnotherUser(): void
    {
        $victim = $this->createUser(['ROLE_USER'], twoFactor: true);
        $attacker = $this->createUser(['ROLE_USER'], twoFactor: true);

        $now = new \DateTimeImmutable();
        $fresh = $this->reload($victim);
        $fresh->requestEmailChange('victim-target@test.invalid', $now);
        $this->em()->flush();

        // L'attaquant forge un jeton valide pour SON PROPRE id mais essaie de
        // l'utiliser pour récupérer l'adresse en attente du compte victime —
        // le jeton porte son propre user_id, jamais celui de la victime.
        $forgedForSelf = static::getContainer()->get(JWTService::class)->generateEmailChangeToken((int) $attacker->getId(), $now->getTimestamp());

        $this->client->request('GET', '/profile/email/confirmer/'.$forgedForSelf);

        self::assertSame('victim-target@test.invalid', $this->reload($victim)->getPendingEmail(), "La demande de la victime ne doit pas être affectée.");
        self::assertNotSame('victim-target@test.invalid', $this->reload($attacker)->getEmail());
    }

    public function testCannotRequestAnAlreadyUsedEmail(): void
    {
        $existing = $this->createUser(['ROLE_USER'], twoFactor: true);
        $user = $this->createUser(['ROLE_USER'], twoFactor: true);
        $this->client->loginUser($user, 'main');

        $crawler = $this->client->request('GET', '/profile/email');
        $form = $crawler->selectButton('Envoyer le lien de confirmation')->form([
            'email_change_request[newEmail]' => $existing->getEmail(),
            'email_change_request[currentPassword]' => self::PASSWORD,
        ]);
        $this->client->submit($form);

        self::assertNull($this->reload($user)->getPendingEmail());
    }

    // ----- Admin : self vs tiers --------------------------------------------

    public function testAdminSelfPasswordChangeRequiresCurrentPassword(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN'], twoFactor: true);
        $this->client->loginUser($admin, 'main');

        $crawler = $this->client->request('GET', '/admin/profil/'.$admin->getId().'/change-password');
        $form = $crawler->selectButton('Changer le mot de passe')->form([
            'change_password_form[currentPassword]' => 'wrong',
            'change_password_form[plainPassword]' => 'New-Admin-Pwd1!',
            'change_password_form[confirmPassword]' => 'New-Admin-Pwd1!',
        ]);
        $this->client->submit($form);

        self::assertStringContainsString('Mot de passe actuel incorrect', (string) $this->client->getResponse()->getContent());
        self::assertTrue($this->passwordHasher()->isPasswordValid($this->reload($admin), self::PASSWORD));
    }

    public function testAdminChangingAnotherUsersPasswordIsUnaffectedByReauth(): void
    {
        // Action administrative légitime, hors périmètre de ce correctif :
        // l'admin ne connaît pas (et ne doit pas avoir besoin de connaître)
        // le mot de passe du compte qu'il gère.
        $admin = $this->createUser(['ROLE_ADMIN'], twoFactor: true);
        $target = $this->createUser(['ROLE_USER'], twoFactor: true);
        $this->client->loginUser($admin, 'main');

        $crawler = $this->client->request('GET', '/admin/profil/'.$target->getId().'/change-password');
        self::assertCount(0, $crawler->filter('input[name="reset_password_form[currentPassword]"], input[name="change_password_form[currentPassword]"]'), 'Aucune re-saisie ne doit être demandée pour le compte d\'un tiers.');

        $form = $crawler->selectButton('Changer le mot de passe')->form([
            'reset_password_form[plainPassword]' => 'Admin-Set-Pwd1!',
            'reset_password_form[confirmPassword]' => 'Admin-Set-Pwd1!',
        ]);
        $this->client->submit($form);

        self::assertTrue($this->passwordHasher()->isPasswordValid($this->reload($target), 'Admin-Set-Pwd1!'));
    }

    private function passwordHasher(): \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface
    {
        return static::getContainer()->get(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class);
    }
}
