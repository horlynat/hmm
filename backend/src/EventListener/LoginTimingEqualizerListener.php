<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Anti-énumération par le temps de réponse (contre-audit B2).
 *
 * Pour un e-mail inconnu, Symfony échoue au chargement de l'utilisateur et ne
 * hache jamais le mot de passe : la réponse « identifiants invalides »
 * arrive alors nettement plus vite (bcrypt/argon2, coût 12 en prod) que pour
 * un compte existant avec un mauvais mot de passe. Le message est identique,
 * mais la durée trahit l'existence du compte.
 *
 * On effectue donc, dans ce seul cas, une vérification factice de même coût
 * (même hacheur que les comptes réels) — les deux chemins d'échec prennent
 * le même temps.
 */
final class LoginTimingEqualizerListener
{
    private const PASSWORD_FIREWALLS = ['main', 'api_login'];

    private ?string $dummyHash = null;

    public function __construct(
        private readonly PasswordHasherFactoryInterface $hasherFactory,
    ) {
    }

    #[AsEventListener(event: LoginFailureEvent::class, priority: 200)]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        if (!\in_array($event->getFirewallName(), self::PASSWORD_FIREWALLS, true)) {
            return;
        }

        $exception = $event->getException();
        if (!$exception instanceof UserNotFoundException && !$exception->getPrevious() instanceof UserNotFoundException) {
            return;
        }

        $passport = $event->getPassport();
        if (null !== $passport && !$passport->hasBadge(PasswordCredentials::class)) {
            return;
        }

        $hasher = $this->hasherFactory->getPasswordHasher(User::class);
        $this->dummyHash ??= $hasher->hash(bin2hex(random_bytes(16)));

        $password = 'x';
        if (null !== $passport) {
            /** @var PasswordCredentials $credentials */
            $credentials = $passport->getBadge(PasswordCredentials::class);
            $password = $credentials->isResolved() ? 'x' : $credentials->getPassword();
        }

        $hasher->verify($this->dummyHash, $password);
    }
}
