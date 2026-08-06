<?php

namespace App\Tests\Security\TwoFactor;

use App\Entity\User;
use App\Security\TwoFactor\BackupCodeAwareTwoFactorProvider;
use App\Security\TwoFactor\BackupCodeManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorProviderInterface;

final class BackupCodeAwareTwoFactorProviderTest extends TestCase
{
    public function testDelegatesToInnerWhenTotpCodeIsValid(): void
    {
        $inner = $this->createStub(TwoFactorProviderInterface::class);
        $inner->method('validateAuthenticationCode')->willReturn(true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $provider = new BackupCodeAwareTwoFactorProvider($inner, new BackupCodeManager(), $em, $this->createStub(LoggerInterface::class));

        $this->assertTrue($provider->validateAuthenticationCode(new User(), '123456'));
    }

    public function testAcceptsAndConsumesAValidBackupCodeWhenTotpFails(): void
    {
        $inner = $this->createStub(TwoFactorProviderInterface::class);
        $inner->method('validateAuthenticationCode')->willReturn(false);

        $manager = new BackupCodeManager();
        $user = new User();
        $codes = $manager->generate($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $provider = new BackupCodeAwareTwoFactorProvider($inner, $manager, $em, $this->createStub(LoggerInterface::class));

        $this->assertTrue($provider->validateAuthenticationCode($user, $codes[0]));
        $this->assertSame(9, $manager->countRemaining($user), 'Le backup code doit être consommé.');
        $this->assertFalse($provider->validateAuthenticationCode($user, $codes[0]), 'Un backup code ne fonctionne qu\'une fois.');
    }

    public function testRejectsWhenBothTotpAndBackupCodeFail(): void
    {
        $inner = $this->createStub(TwoFactorProviderInterface::class);
        $inner->method('validateAuthenticationCode')->willReturn(false);

        $manager = new BackupCodeManager();
        $user = new User();
        $manager->generate($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $provider = new BackupCodeAwareTwoFactorProvider($inner, $manager, $em, $this->createStub(LoggerInterface::class));

        $this->assertFalse($provider->validateAuthenticationCode($user, 'wrong-code'));
    }
}
