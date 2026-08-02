<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Vérifie le statut du compte (email vérifié, compte actif) à chaque
 * authentification, sur TOUS les firewalls qui le déclarent (formulaire web
 * ET API JWT). Auparavant ces contrôles vivaient dans le UserBadge loader de
 * SecurityAuthenticator : ils ne protégeaient donc que le login web (un compte
 * désactivé pouvait toujours obtenir un JWT via /api/login_check), et
 * s'exécutaient AVANT la validation du mot de passe — ce qui permettait
 * d'énumérer les comptes (le message « compte désactivé » révélait l'existence
 * de l'email sans connaître le mot de passe).
 *
 * checkPostAuth s'exécute APRÈS la validation du mot de passe : les messages
 * de statut ne sont donc montrés qu'à quelqu'un qui a déjà prouvé qu'il connaît
 * le mot de passe, ce qui supprime le vecteur d'énumération.
 */
final class UserChecker implements UserCheckerInterface
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        // Volontairement vide : aucun contrôle de statut avant la validation du
        // mot de passe, pour ne pas révéler l'existence ou l'état d'un compte
        // à qui ne connaît pas le mot de passe (anti-énumération).
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException("Votre compte n'est pas encore vérifié. Consultez vos emails pour l'activer.");
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Votre compte a été désactivé. Contactez le support pour plus d\'informations.');
        }

        // scheb/2fa-bundle n'est câblé que sur le firewall web `main` (cf.
        // security.yaml) : le firewall `api_login` (émission du JWT pour le
        // frontend Next.js) ne pose jamais de second facteur. Sans ce garde-fou,
        // un compte protégé par la 2FA obtenait quand même un JWT valide avec le
        // seul mot de passe — la 2FA était donc contournable via l'API. On bloque
        // ici, au même point que les contrôles ci-dessus (après le mot de passe,
        // donc sans risque d'énumération), en attendant un vrai flux 2FA côté API.
        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request
            && str_starts_with($request->getPathInfo(), '/api/login_check')
            && $user->isTotpAuthenticationEnabled()
        ) {
            throw new CustomUserMessageAccountStatusException(
                "La double authentification est activée sur ce compte : connectez-vous depuis l'espace back-office pour l'instant.",
            );
        }
    }
}
