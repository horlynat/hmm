<?php

namespace App\Tests\Security\Voter;

use App\Entity\User;
use App\Security\Voter\UserVoter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Régression PAM : la protection "seul un Super Admin peut éditer/supprimer
 * un Super Admin" doit se baser sur le DROIT ACQUIS de la cible
 * (hasSuperAdminEntitlement()), pas sur son élévation momentanée — sinon un
 * Super Admin dormant (le mode par défaut, cf. User::$superAdminElevatedUntil)
 * perdrait sa protection la majorité du temps.
 */
final class UserVoterSuperAdminProtectionTest extends TestCase
{
    public function testDormantSuperAdminTargetStaysProtectedFromRegularAdmin(): void
    {
        $target = new User();
        $target->setRoles(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']); // droit acquis, non élevé (dormant)
        self::assertFalse($target->isSuperAdminElevated());

        $regularAdmin = new User();
        $regularAdmin->setRoles(['ROLE_ADMIN']);

        $voter = $this->makeVoter();

        $decision = $voter->vote($this->tokenFor($regularAdmin), $target, [UserVoter::EDIT]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $decision, 'Un admin non élevé ne doit pas pouvoir éditer un Super Admin dormant.');
    }

    public function testElevatedSuperAdminCanEditDormantSuperAdminTarget(): void
    {
        $target = new User();
        $target->setRoles(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);

        $elevatedActor = new User();
        $elevatedActor->setRoles(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
        $elevatedActor->setSuperAdminElevatedUntil(new \DateTimeImmutable('+30 minutes'));

        $voter = $this->makeVoter();

        $decision = $voter->vote($this->tokenFor($elevatedActor), $target, [UserVoter::EDIT]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $decision);
    }

    /**
     * Pas de vraie expansion de hiérarchie nécessaire ici : chaque User de
     * test porte déjà, via getRoles(), l'ensemble exact de rôles pertinent
     * (SUPER_ADMIN n'y figure que si réellement élevé) — une hiérarchie
     * identité (aucune expansion) suffit à distinguer les deux scénarios.
     */
    private function makeVoter(): UserVoter
    {
        $roleHierarchy = $this->createStub(RoleHierarchyInterface::class);
        $roleHierarchy->method('getReachableRoleNames')->willReturnArgument(0);

        return new UserVoter($roleHierarchy, new NullLogger());
    }

    private function tokenFor(User $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
