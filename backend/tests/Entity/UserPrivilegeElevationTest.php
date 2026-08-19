<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * PAM : ROLE_SUPER_ADMIN reste dormant tant qu'il n'est pas activement
 * élevé — cf. docblock de User::$superAdminElevatedUntil. Ce fichier couvre
 * spécifiquement cette mécanique ; le reste du comportement de User (email,
 * mot de passe…) n'a pas de suite dédiée préexistante.
 */
final class UserPrivilegeElevationTest extends TestCase
{
    public function testGetRolesExcludesSuperAdminWhenNotElevated(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);

        self::assertNotContains('ROLE_SUPER_ADMIN', $user->getRoles());
        self::assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testGetRolesIncludesSuperAdminWhileElevationWindowIsActive(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
        $user->setSuperAdminElevatedUntil(new \DateTimeImmutable('+30 minutes'));

        self::assertContains('ROLE_SUPER_ADMIN', $user->getRoles());
    }

    public function testGetRolesExcludesSuperAdminOncePastElevationWindow(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
        $user->setSuperAdminElevatedUntil(new \DateTimeImmutable('-1 minute'));

        self::assertNotContains('ROLE_SUPER_ADMIN', $user->getRoles());
        self::assertFalse($user->isSuperAdminElevated());
    }

    public function testHasSuperAdminEntitlementIsTrueRegardlessOfElevationState(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);

        // Dormant : pas élevé, mais l'entitlement reste vrai.
        self::assertTrue($user->hasSuperAdminEntitlement());
        self::assertFalse($user->isSuperAdminElevated());
    }

    public function testUserWithoutSuperAdminRoleHasNoEntitlement(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        self::assertFalse($user->hasSuperAdminEntitlement());
    }

    /**
     * getPrimaryRole() sert à l'affichage/routage administratif (page Rôles,
     * AccountLinkResolver) — un Super Admin dormant doit y rester classé
     * Super Admin, contrairement à getRoles() qui gouverne l'autorisation.
     */
    public function testGetPrimaryRoleReflectsEntitlementEvenWhenDormant(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);

        self::assertSame('ROLE_SUPER_ADMIN', $user->getPrimaryRole());
        self::assertFalse($user->isSuperAdminElevated());
    }
}
