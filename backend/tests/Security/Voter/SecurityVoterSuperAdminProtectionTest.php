<?php

namespace App\Tests\Security\Voter;

use App\Entity\User;
use App\Security\SuperAdminElevationContext;
use App\Security\Voter\SecurityVoter;
use App\Service\PermissionRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

/**
 * P1 #9 — MANAGE_2FA / FORCE_LOGOUT sur un compte super-admin : même garde
 * que UserVoter (cible protégée sur son DROIT acquis, acteur devant être
 * réellement élevé).
 */
final class SecurityVoterSuperAdminProtectionTest extends TestCase
{
    protected function tearDown(): void
    {
        SuperAdminElevationContext::reset();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function attributes(): iterable
    {
        yield 'MANAGE_2FA' => [SecurityVoter::MANAGE_2FA];
        yield 'FORCE_LOGOUT' => [SecurityVoter::FORCE_LOGOUT];
    }

    #[DataProvider('attributes')]
    public function testRegularAdminCannotTargetDormantSuperAdmin(string $attribute): void
    {
        $target = $this->user('target@test.invalid', ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
        $actor = $this->user('admin@test.invalid', ['ROLE_ADMIN']);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($actor, $attribute, $target));
    }

    #[DataProvider('attributes')]
    public function testDormantSuperAdminActorCannotTargetAnotherSuperAdmin(string $attribute): void
    {
        $target = $this->user('target@test.invalid', ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
        $actor = $this->user('dormant@test.invalid', ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($actor, $attribute, $target));
    }

    #[DataProvider('attributes')]
    public function testDbElevationWithoutSessionBindingIsNotEnough(string $attribute): void
    {
        $target = $this->user('target@test.invalid', ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
        $actor = $this->user('elevated@test.invalid', ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
        $actor->setSuperAdminElevatedUntil(new \DateTimeImmutable('+30 minutes'));
        // Pas de SuperAdminElevationContext::grant() : cas d'un JWT ou d'une
        // autre session du même compte.

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($actor, $attribute, $target));
    }

    #[DataProvider('attributes')]
    public function testElevatedSuperAdminCanTargetSuperAdmin(string $attribute): void
    {
        $target = $this->user('target@test.invalid', ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
        $actor = $this->user('elevated@test.invalid', ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
        $until = new \DateTimeImmutable('+30 minutes');
        $actor->setSuperAdminElevatedUntil($until);
        SuperAdminElevationContext::grant($actor->getEmail(), $until->getTimestamp());

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($actor, $attribute, $target));
    }

    #[DataProvider('attributes')]
    public function testRegularAdminKeepsAccessOnRegularAccounts(string $attribute): void
    {
        $target = $this->user('client@test.invalid', ['ROLE_USER']);
        $actor = $this->user('admin@test.invalid', ['ROLE_ADMIN']);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($actor, $attribute, $target));
    }

    /**
     * @param list<string> $roles
     */
    private function user(string $email, array $roles): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setRoles($roles);

        return $user;
    }

    private function vote(User $actor, string $attribute, User $target): int
    {
        $hierarchy = new RoleHierarchy([
            'ROLE_EDITOR' => ['ROLE_USER'],
            'ROLE_MODERATOR' => ['ROLE_EDITOR'],
            'ROLE_MANAGER' => ['ROLE_MODERATOR'],
            'ROLE_ADMIN' => ['ROLE_MANAGER'],
            'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN'],
        ]);
        $registry = $this->createStub(PermissionRegistry::class);
        $registry->method('resolveRole')->willReturnArgument(1);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($actor);

        return (new SecurityVoter($hierarchy, new NullLogger(), $registry))->vote($token, $target, [$attribute]);
    }
}
