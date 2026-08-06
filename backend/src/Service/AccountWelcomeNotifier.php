<?php

namespace App\Service;

use App\Entity\User;

/**
 * Notifie un utilisateur dont le compte vient d'être créé directement par un
 * administrateur (donc sans passer par l'auto-inscription, qui envoie déjà
 * son propre email de confirmation — voir RegistrationController /
 * ClientRegistrationProcessor / CollaboratorRegistrationProcessor).
 *
 * Le mot de passe est défini par l'admin au moment de la création et n'est
 * jamais inclus dans cet email ; l'utilisateur peut le récupérer via « mot de
 * passe oublié » s'il ne l'a pas reçu par un autre canal.
 */
final class AccountWelcomeNotifier
{
    public function __construct(
        private readonly EmailManager $emailManager,
        private readonly AccountLinkResolver $accountLinkResolver,
    ) {
    }

    public function accountCreated(User $user, string $roleLabel): void
    {
        $email = $user->getEmail();
        if ('' === $email) {
            return;
        }

        $loginUrl = $this->accountLinkResolver->resolve($user, 'login', [], '/connexion');

        $this->emailManager->sendAsync(
            to: $email,
            subject: 'Votre compte a été créé',
            template: 'account_welcome',
            context: [
                'fullName' => $user->getFullName() ?: $email,
                'roleLabel' => $roleLabel,
                'loginUrl' => $loginUrl,
            ],
        );
    }
}
