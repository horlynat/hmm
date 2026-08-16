<?php

namespace App\Tests\Security\TwoFactor;

use App\Security\TwoFactor\PendingTotpUser;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;

final class PendingTotpUserTest extends TestCase
{
    public function testExposesTheGivenUsernameAndSecretAsAnEnabledTotpUser(): void
    {
        $user = new PendingTotpUser('client@example.com', 'JBSWY3DPEHPK3PXP');

        $this->assertTrue($user->isTotpAuthenticationEnabled());
        $this->assertSame('client@example.com', $user->getTotpAuthenticationUsername());

        $config = $user->getTotpAuthenticationConfiguration();
        $this->assertInstanceOf(TotpConfigurationInterface::class, $config);
        $this->assertSame('JBSWY3DPEHPK3PXP', $config->getSecret());
    }

    public function testUsernameCanBeNull(): void
    {
        // getTotpAuthenticationUsername() de User peut renvoyer null (cf.
        // TotpTwoFactorInterface) — ce constructeur ne doit pas l'exiger.
        $user = new PendingTotpUser(null, 'JBSWY3DPEHPK3PXP');

        $this->assertNull($user->getTotpAuthenticationUsername());
    }
}
