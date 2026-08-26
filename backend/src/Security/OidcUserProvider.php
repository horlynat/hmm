<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Drenso\OidcBundle\Exception\OidcException;
use Drenso\OidcBundle\Model\OidcTokens;
use Drenso\OidcBundle\Model\OidcUserData;
use Drenso\OidcBundle\Security\UserProvider\OidcUserProviderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Pont entre le firewall OIDC (SSO, cf. security.yaml → firewalls.main.oidc)
 * et le référentiel de comptes local (UserRepository, même source que le
 * provider `app_user_provider` de la connexion classique) — configuré avec
 * `user_identifier_property: email`, donc $userIdentifier reçu ici est déjà
 * l'email résolu depuis les claims du fournisseur d'identité.
 *
 * Décision de sécurité volontaire : ensureUserExists() ne provisionne JAMAIS
 * de compte automatiquement. Le SSO n'est un SECOND chemin de connexion que
 * pour un compte DÉJÀ créé côté admin (avec le bon rôle attribué à la main) —
 * jamais un moyen pour n'importe quel utilisateur du fournisseur d'identité
 * de s'auto-créer un accès. Si aucun compte local ne correspond à l'email,
 * l'authentification échoue proprement plutôt que de créer un compte
 * ROLE_USER surprise, ou pire, de mal interpréter un claim et créer un compte
 * sur-privilégié.
 *
 * @implements OidcUserProviderInterface<User>
 */
class OidcUserProvider implements OidcUserProviderInterface
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function ensureUserExists(string $userIdentifier, OidcUserData $userData, OidcTokens $tokens): void
    {
        if (null === $this->userRepository->findOneBy(['email' => $userIdentifier])) {
            throw new OidcException(sprintf(
                'Aucun compte local pour "%s" — le SSO ne provisionne pas de nouveau compte, contactez un administrateur pour vous en créer un au préalable.',
                $userIdentifier,
            ));
        }
    }

    public function loadOidcUser(string $userIdentifier): UserInterface
    {
        return $this->loadUserByIdentifier($userIdentifier);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userRepository->findOneBy(['email' => $identifier]);
        if (!$user) {
            throw new UserNotFoundException(sprintf('Compte "%s" introuvable.', $identifier));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}
