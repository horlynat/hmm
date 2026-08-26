<?php

namespace App\Security\Api;

use App\Entity\User;
use App\Service\JWTService;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Décore le success handler JWT (lexik) pour intercaler un second facteur sur
 * /api/login_check quand le compte l'a activé.
 *
 * Avant ce flux, UserChecker::checkPostAuth() bloquait purement et simplement
 * la connexion API d'un compte 2FA (voir son historique) : le mot de passe
 * seul ne devait jamais suffire, mais aucun vrai second facteur n'existait
 * côté API (scheb/2fa-bundle n'est câblé que sur le firewall web `main`,
 * stateful, cf. security.yaml). Ce blocage est désormais inutile — remplacé
 * par le flux ci-dessous.
 *
 * Le mot de passe est déjà vérifié quand ce handler s'exécute (c'est un
 * success handler). Pour un compte sans 2FA, rien ne change : on délègue au
 * handler lexik standard, qui émet directement le vrai jeton d'accès. Pour un
 * compte 2FA, on émet à la place un jeton de défi (JWTService, PAS lexik —
 * voir le commentaire de generateApiTwoFactorChallengeToken() : les deux
 * formats ne se recoupent pas, ce jeton est donc structurellement inutilisable
 * comme jeton Bearer sur l'API). ApiTwoFactorLoginController l'échange contre
 * le vrai jeton d'accès une fois le code TOTP (ou un code de récupération)
 * vérifié.
 */
#[AsDecorator('lexik_jwt_authentication.handler.authentication_success')]
final class TwoFactorAwareJwtSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        #[AutowireDecorated] private readonly AuthenticationSuccessHandlerInterface $inner,
        private readonly JWTService $jwt,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();

        if (!$user instanceof User || !$user->isTotpAuthenticationEnabled() || null === $user->getId()) {
            return $this->inner->onAuthenticationSuccess($request, $token);
        }

        return new JsonResponse([
            'twoFactorRequired' => true,
            'challengeToken' => $this->jwt->generateApiTwoFactorChallengeToken($user->getId()),
        ]);
    }
}
