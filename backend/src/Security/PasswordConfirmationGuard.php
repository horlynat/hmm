<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Vérification du mot de passe ACTUEL d'un utilisateur déjà authentifié
 * (step-up), avec limitation de débit par compte (limiteur
 * `password_confirmation`, 5 essais / 15 min).
 *
 * Point unique pour tous les écrans « confirmez votre mot de passe » : sans
 * limite, chacun d'eux transformait une session ou un JWT volé en oracle de
 * mot de passe illimité (P2).
 */
final class PasswordConfirmationGuard
{
    public const OK = 'ok';
    public const INVALID = 'invalid';
    public const THROTTLED = 'throttled';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        #[Autowire(service: 'limiter.password_confirmation')]
        private readonly RateLimiterFactoryInterface $limiterFactory,
    ) {
    }

    /**
     * @return self::OK|self::INVALID|self::THROTTLED
     */
    public function check(User $user, mixed $plainPassword): string
    {
        $limiter = $this->limiterFactory->create('pwd_confirm_'.($user->getId() ?? $user->getUserIdentifier()));
        if (!$limiter->consume(1)->isAccepted()) {
            return self::THROTTLED;
        }

        if (!\is_string($plainPassword) || '' === $plainPassword || \strlen($plainPassword) > 4096
            || !$this->passwordHasher->isPasswordValid($user, $plainPassword)) {
            return self::INVALID;
        }

        $limiter->reset();

        return self::OK;
    }
}
