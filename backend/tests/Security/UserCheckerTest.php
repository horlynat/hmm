<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
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

    /** Requête factice sur une route neutre (ni /api/login_check, ni /login). */
    private function createChecker(): UserChecker
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/'));

        return new UserChecker($requestStack);
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
}
