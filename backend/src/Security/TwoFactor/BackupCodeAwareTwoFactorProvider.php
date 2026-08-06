<?php

namespace App\Security\TwoFactor;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Décore le provider TOTP de Scheb pour accepter aussi un code de récupération
 * (backup code) à la place du code TOTP à la connexion.
 *
 * Le support natif des backup codes de Scheb n'est pas disponible dans cette
 * installation (classes du paquet absentes du vendor) : on l'implémente donc
 * ici sans y dépendre. Quand le code TOTP échoue, on tente les codes de
 * récupération ; un code valide est consommé (usage unique) et journalisé.
 */
#[AsDecorator('scheb_two_factor.security.totp.provider')]
final class BackupCodeAwareTwoFactorProvider implements TwoFactorProviderInterface
{
    public function __construct(
        #[AutowireDecorated] private readonly TwoFactorProviderInterface $inner,
        private readonly BackupCodeManager $backupCodeManager,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'monolog.logger.security_errors')]
        private readonly LoggerInterface $securityLogger,
    ) {
    }

    public function beginAuthentication(AuthenticationContextInterface $context): bool
    {
        return $this->inner->beginAuthentication($context);
    }

    public function needsPreparation(): bool
    {
        return $this->inner->needsPreparation();
    }

    public function prepareAuthentication(object $user): void
    {
        $this->inner->prepareAuthentication($user);
    }

    public function validateAuthenticationCode(object $user, string $authenticationCode): bool
    {
        if ($this->inner->validateAuthenticationCode($user, $authenticationCode)) {
            return true;
        }

        // Code TOTP invalide : c'est peut-être un code de récupération.
        if ($user instanceof User && $this->backupCodeManager->isValid($user, $authenticationCode)) {
            $this->backupCodeManager->invalidate($user, $authenticationCode);
            $this->entityManager->flush();

            $this->securityLogger->warning('Connexion 2FA via un code de récupération.', [
                'user' => $user->getUserIdentifier(),
                'remaining_backup_codes' => $this->backupCodeManager->countRemaining($user),
            ]);

            return true;
        }

        return false;
    }

    public function getFormRenderer(): TwoFactorFormRendererInterface
    {
        return $this->inner->getFormRenderer();
    }
}
