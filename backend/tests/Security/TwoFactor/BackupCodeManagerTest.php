<?php

namespace App\Tests\Security\TwoFactor;

use App\Entity\User;
use App\Security\TwoFactor\BackupCodeManager;
use PHPUnit\Framework\TestCase;

final class BackupCodeManagerTest extends TestCase
{
    public function testGenerateReturnsTenPlainCodesAndStoresHashes(): void
    {
        $manager = new BackupCodeManager();
        $user = new User();

        $codes = $manager->generate($user);

        $this->assertCount(BackupCodeManager::CODE_COUNT, $codes);
        $this->assertCount(BackupCodeManager::CODE_COUNT, $user->getBackupCodes());

        // Les codes en clair ne doivent jamais être stockés tels quels.
        foreach ($codes as $plain) {
            $this->assertNotContains($plain, $user->getBackupCodes());
            $this->assertMatchesRegularExpression('/^[0-9a-f]{4}-[0-9a-f]{4}$/', $plain);
        }
    }

    public function testAValidCodeIsAccepted(): void
    {
        $manager = new BackupCodeManager();
        $user = new User();
        $codes = $manager->generate($user);

        $this->assertTrue($manager->isValid($user, $codes[0]));
    }

    public function testCodeAcceptedRegardlessOfDashOrCase(): void
    {
        $manager = new BackupCodeManager();
        $user = new User();
        $codes = $manager->generate($user);

        $withoutDash = str_replace('-', '', $codes[0]);
        $upperCased = strtoupper($codes[0]);

        $this->assertTrue($manager->isValid($user, $withoutDash));
        $this->assertTrue($manager->isValid($user, $upperCased));
        $this->assertTrue($manager->isValid($user, ' '.$codes[0].' '));
    }

    public function testAnUnknownCodeIsRejected(): void
    {
        $manager = new BackupCodeManager();
        $user = new User();
        $manager->generate($user);

        $this->assertFalse($manager->isValid($user, 'ffff-ffff-nope'));
    }

    public function testInvalidateConsumesTheCodeExactlyOnce(): void
    {
        $manager = new BackupCodeManager();
        $user = new User();
        $codes = $manager->generate($user);

        $this->assertSame(10, $manager->countRemaining($user));

        $manager->invalidate($user, $codes[0]);

        $this->assertSame(9, $manager->countRemaining($user));
        $this->assertFalse($manager->isValid($user, $codes[0]), 'Un code consommé ne doit plus être valide.');
        $this->assertTrue($manager->isValid($user, $codes[1]), 'Les autres codes restent valides.');
    }

    public function testRegeneratingInvalidatesThePreviousBatch(): void
    {
        $manager = new BackupCodeManager();
        $user = new User();
        $oldCodes = $manager->generate($user);

        $manager->generate($user);

        $this->assertFalse($manager->isValid($user, $oldCodes[0]), 'Les anciens codes ne doivent plus fonctionner après régénération.');
        $this->assertSame(10, $manager->countRemaining($user));
    }

    public function testCodesAreUnique(): void
    {
        $codes = (new BackupCodeManager())->generate(new User());

        $this->assertSame($codes, array_values(array_unique($codes)));
    }
}
