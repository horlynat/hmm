<?php

namespace App\Tests\Security\Voter;

use App\Entity\Incident;
use App\Entity\User;
use App\Security\Voter\IncidentVoter;
use App\Service\PermissionRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Le mécanisme RBAC dynamique lui-même (PermissionRegistry, hiérarchie de
 * rôles) est déjà couvert par AbstractRoleVoterTest — ce test se concentre
 * sur la seule logique propre à IncidentVoter : quel seuil pour quelle
 * action/sujet.
 */
final class IncidentVoterTest extends TestCase
{
    public function testViewIsGrantedToAdminWithoutSubject(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(IncidentVoter::VIEW, null, ['ROLE_ADMIN']));
    }

    public function testViewIsGrantedToAdminWithAnIncidentSubject(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(IncidentVoter::VIEW, new Incident(), ['ROLE_ADMIN']));
    }

    public function testViewIsDeniedBelowAdmin(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(IncidentVoter::VIEW, null, ['ROLE_MANAGER']));
    }

    public function testCreateIsDeniedBelowAdmin(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(IncidentVoter::CREATE, null, ['ROLE_MANAGER']));
    }

    public function testEditRequiresAnIncidentSubject(): void
    {
        // Pas de sujet -> attribut non supporté par ce Voter -> ACCESS_ABSTAIN,
        // pas ACCESS_DENIED (aucun Voter du système ne se prononce).
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(IncidentVoter::EDIT, null, ['ROLE_ADMIN']));
    }

    public function testDeleteRequiresSuperAdminEvenForAnAdmin(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(IncidentVoter::DELETE, new Incident(), ['ROLE_ADMIN']));
    }

    public function testDeleteIsGrantedToSuperAdmin(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(IncidentVoter::DELETE, new Incident(), ['ROLE_SUPER_ADMIN']));
    }

    /**
     * @param string[] $reachableRoles
     */
    private function vote(string $attribute, mixed $subject, array $reachableRoles): int
    {
        $roleHierarchy = $this->createStub(RoleHierarchyInterface::class);
        $roleHierarchy->method('getReachableRoleNames')->willReturn($reachableRoles);

        $permissionRegistry = $this->createStub(PermissionRegistry::class);
        // Pas de personnalisation en base pour ce test : le seuil codé en dur fait foi.
        $permissionRegistry->method('resolveRole')->willReturnArgument(1);

        $voter = new IncidentVoter($roleHierarchy, new NullLogger(), $permissionRegistry);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(new User());

        return $voter->vote($token, $subject, [$attribute]);
    }
}
