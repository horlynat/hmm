<?php

namespace App\Tests\Security\Voter;

use App\Entity\User;
use App\Security\Voter\AbstractRoleVoter;
use App\Service\PermissionRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Vérifie le point d'intégration du RBAC dynamique : AbstractRoleVoter doit
 * consulter PermissionRegistry::resolveRole() avant de trancher, et la
 * décision doit réellement changer selon ce que le registre renvoie — pas
 * seulement "être appelé" (cf. TestVoter, sous-classe minimale concrète, même
 * principe que les autres Voters de l'app).
 */
final class AbstractRoleVoterTest extends TestCase
{
    public function testGrantsAccessWhenUserHasCodedDefaultRoleAndNoOverride(): void
    {
        $permissionRegistry = $this->createStub(PermissionRegistry::class);
        $permissionRegistry->method('resolveRole')->willReturnArgument(1); // pas de changement : renvoie le fallback tel quel

        $voter = $this->makeVoter($permissionRegistry, reachableRoles: ['ROLE_EDITOR', 'ROLE_USER']);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->tokenFor(new User()), null, ['TEST_ACTION']));
    }

    public function testDeniesAccessWhenUserLacksCodedDefaultRoleAndNoOverride(): void
    {
        $permissionRegistry = $this->createStub(PermissionRegistry::class);
        $permissionRegistry->method('resolveRole')->willReturnArgument(1);

        $voter = $this->makeVoter($permissionRegistry, reachableRoles: ['ROLE_USER']);

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->tokenFor(new User()), null, ['TEST_ACTION']));
    }

    /**
     * Le cœur du RBAC dynamique : le registre relève le seuil au-delà de ce
     * que le Voter a codé en dur (ROLE_EDITOR → ROLE_MANAGER) — un utilisateur
     * qui avait accès avant doit maintenant être refusé.
     */
    public function testDbOverrideRaisingTheThresholdRevokesAccess(): void
    {
        $permissionRegistry = $this->createStub(PermissionRegistry::class);
        $permissionRegistry->method('resolveRole')->willReturn('ROLE_MANAGER');

        $voter = $this->makeVoter($permissionRegistry, reachableRoles: ['ROLE_EDITOR', 'ROLE_USER']);

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->tokenFor(new User()), null, ['TEST_ACTION']));
    }

    /**
     * Symétrique : le registre abaisse le seuil (ROLE_EDITOR → ROLE_USER) —
     * un utilisateur qui n'avait initialement pas accès l'obtient désormais.
     */
    public function testDbOverrideLoweringTheThresholdGrantsAccess(): void
    {
        $permissionRegistry = $this->createStub(PermissionRegistry::class);
        $permissionRegistry->method('resolveRole')->willReturn('ROLE_USER');

        $voter = $this->makeVoter($permissionRegistry, reachableRoles: ['ROLE_USER']);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->tokenFor(new User()), null, ['TEST_ACTION']));
    }

    public function testPassesAttributeAndCodedDefaultRoleToRegistry(): void
    {
        $permissionRegistry = $this->createMock(PermissionRegistry::class);
        $permissionRegistry->expects($this->once())
            ->method('resolveRole')
            ->with('TEST_ACTION', 'ROLE_EDITOR')
            ->willReturn('ROLE_EDITOR');

        $voter = $this->makeVoter($permissionRegistry, reachableRoles: ['ROLE_EDITOR']);

        $voter->vote($this->tokenFor(new User()), null, ['TEST_ACTION']);
    }

    /**
     * @param string[] $reachableRoles
     */
    private function makeVoter(PermissionRegistry $permissionRegistry, array $reachableRoles): AbstractRoleVoter
    {
        $roleHierarchy = $this->createStub(RoleHierarchyInterface::class);
        $roleHierarchy->method('getReachableRoleNames')->willReturn($reachableRoles);

        return new class($roleHierarchy, new NullLogger(), $permissionRegistry) extends AbstractRoleVoter {
            protected function getRequiredRole(string $attribute, mixed $subject): ?string
            {
                return 'TEST_ACTION' === $attribute && null === $subject ? 'ROLE_EDITOR' : null;
            }
        };
    }

    private function tokenFor(User $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
