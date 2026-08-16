<?php

namespace App\Security\TwoFactor;

use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface as TotpTwoFactorInterface;

/**
 * Utilisateur "fantôme" au secret TOTP en attente de confirmation, uniquement
 * pour générer/vérifier un QR code AVANT que le secret ne soit committé sur
 * l'entité User réelle — jamais activer une 2FA que l'utilisateur ne serait
 * pas en mesure de satisfaire (secret mal scanné, appli non synchronisée...).
 *
 * Partagé entre le flux web en session (TwoFactorController) et le flux API
 * stateless (ApiTwoFactorSetupController) : seule différence entre les deux,
 * l'endroit où vit le secret tant qu'il n'est pas confirmé (session côté web ;
 * renvoyé au client et rattaché à chaque appel côté API, cf. le commentaire
 * de ApiTwoFactorSetupController::confirm()).
 */
final class PendingTotpUser implements TotpTwoFactorInterface
{
    public function __construct(
        private readonly ?string $username,
        private readonly string $secret,
    ) {
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return true;
    }

    public function getTotpAuthenticationUsername(): ?string
    {
        return $this->username;
    }

    public function getTotpAuthenticationConfiguration(): TotpConfigurationInterface
    {
        return new TotpConfiguration($this->secret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }
}
