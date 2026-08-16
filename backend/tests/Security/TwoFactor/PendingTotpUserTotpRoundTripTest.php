<?php

namespace App\Tests\Security\TwoFactor;

use App\Security\TwoFactor\PendingTotpUser;
use OTPHP\TOTP;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticator;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Preuve bout-en-bout (avec les vraies classes scheb/2fa-totp, sans mock) que
 * PendingTotpUser porte correctement un secret à travers generateSecret() /
 * checkCode() — le chemin réellement emprunté par TwoFactorController::setup()
 * (web) ET ApiTwoFactorSetupController::confirm() (API), tous deux construits
 * sur cette classe. Un vrai code TOTP est calculé "à la main" via OTPHP (la
 * lib sous-jacente de scheb) pour l'instant présent, exactement comme le
 * ferait une véritable application d'authentification.
 */
final class PendingTotpUserTotpRoundTripTest extends TestCase
{
    private function createAuthenticator(): TotpAuthenticator
    {
        return new TotpAuthenticator(new TotpFactory(null, 'HMM Test', []), new EventDispatcher(), 1);
    }

    public function testAValidCodeComputedForTheGivenSecretIsAccepted(): void
    {
        $authenticator = $this->createAuthenticator();
        $secret = $authenticator->generateSecret();

        $pendingUser = new PendingTotpUser('client@example.com', $secret);

        // Code calculé indépendamment de PendingTotpUser/TotpAuthenticator, à
        // partir du même secret — simule une vraie appli d'authentification
        // correctement synchronisée.
        $currentCode = TOTP::create($secret)->now();

        $this->assertTrue($authenticator->checkCode($pendingUser, $currentCode));
    }

    public function testAWrongCodeIsRejected(): void
    {
        $authenticator = $this->createAuthenticator();
        $secret = $authenticator->generateSecret();
        $pendingUser = new PendingTotpUser('client@example.com', $secret);

        $wrongCode = '000000' === TOTP::create($secret)->now() ? '111111' : '000000';

        $this->assertFalse($authenticator->checkCode($pendingUser, $wrongCode));
    }

    public function testACodeComputedForADifferentSecretIsRejected(): void
    {
        // Preuve directe que confirm() ne peut pas être trompé en renvoyant un
        // secret différent de celui réellement scanné par l'application : le
        // code doit correspondre EXACTEMENT au secret fourni.
        $authenticator = $this->createAuthenticator();
        $realSecret = $authenticator->generateSecret();
        $otherSecret = $authenticator->generateSecret();

        $pendingUser = new PendingTotpUser('client@example.com', $realSecret);
        $codeForOtherSecret = TOTP::create($otherSecret)->now();

        $this->assertFalse($authenticator->checkCode($pendingUser, $codeForOtherSecret));
    }
}
