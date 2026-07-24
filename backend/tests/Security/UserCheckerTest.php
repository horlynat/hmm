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

    public function testPreAuthNeverThrows(): void
    {
        // checkPreAuth ne doit jamais lever, même pour un compte bloqué :
        // les contrôles de statut appartiennent au post-auth (anti-énumération).
        (new UserChecker())->checkPreAuth($this->createUser(false, false));
        $this->addToAssertionCount(1);
    }

    public function testActiveAndVerifiedUserPassesPostAuth(): void
    {
        (new UserChecker())->checkPostAuth($this->createUser(true, true));
        $this->addToAssertionCount(1);
    }

    public function testUnverifiedUserIsRejectedPostAuth(): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessageMatches('/vérifié/');

        (new UserChecker())->checkPostAuth($this->createUser(true, false));
    }

    public function testDisabledUserIsRejectedPostAuth(): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessageMatches('/désactivé/');

        (new UserChecker())->checkPostAuth($this->createUser(false, true));
    }

    public function testNonAppUserIsIgnored(): void
    {
        // Un UserInterface qui n'est pas notre entité (improbable, mais la
        // signature l'autorise) ne doit pas faire planter le checker.
        (new UserChecker())->checkPostAuth(new InMemoryUser('x', 'y'));
        $this->addToAssertionCount(1);
    }
}
