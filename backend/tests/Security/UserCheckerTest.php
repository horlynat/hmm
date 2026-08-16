<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class UserCheckerTest extends TestCase
{
    private function createUser(bool $active, bool $verified): User
    {
        $user = new User();
        $user->setIsActive($active);
        $user->setIsVerified($verified);

        return $user;
    }

    private function createChecker(): UserChecker
    {
        return new UserChecker();
    }

    public function testPreAuthNeverThrows(): void
    {
        // checkPreAuth ne doit jamais lever, même pour un compte bloqué :
        // les contrôles de statut appartiennent au post-auth (anti-énumération).
        $this->createChecker()->checkPreAuth($this->createUser(false, false));
        $this->addToAssertionCount(1);
    }

    public function testActiveAndVerifiedUserPassesPostAuth(): void
    {
        $this->createChecker()->checkPostAuth($this->createUser(true, true));
        $this->addToAssertionCount(1);
    }

    public function testUnverifiedUserIsRejectedPostAuth(): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessageMatches('/vérifié/');

        $this->createChecker()->checkPostAuth($this->createUser(true, false));
    }

    public function testDisabledUserIsRejectedPostAuth(): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessageMatches('/désactivé/');

        $this->createChecker()->checkPostAuth($this->createUser(false, true));
    }

    public function testNonAppUserIsIgnored(): void
    {
        // Un UserInterface qui n'est pas notre entité (improbable, mais la
        // signature l'autorise) ne doit pas faire planter le checker.
        $this->createChecker()->checkPostAuth(new InMemoryUser('x', 'y'));
        $this->addToAssertionCount(1);
    }

    public function testActiveVerifiedTwoFactorUserPassesPostAuth(): void
    {
        // Régression : ce checker bloquait autrefois purement et simplement un
        // compte 2FA sur /api/login_check (aucun vrai second facteur n'existait
        // côté API). C'est maintenant TwoFactorAwareJwtSuccessHandler qui prend
        // le relais après ce checker — celui-ci ne doit plus rien savoir de la
        // 2FA ni de la route courante.
        $user = $this->createUser(true, true);
        $user->setTotpSecret('JBSWY3DPEHPK3PXP');
        $user->setIsTwoFactorEnabled(true);

        $this->createChecker()->checkPostAuth($user);
        $this->addToAssertionCount(1);
    }
}
