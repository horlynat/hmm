<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\TwoFactor\BackupCodeManager;
use App\Service\JWTService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Second temps de la connexion API pour un compte protégé par la 2FA :
 * échange le jeton de défi (émis par TwoFactorAwareJwtSuccessHandler à la
 * place du vrai jeton d'accès) contre ce dernier, une fois le code TOTP — ou
 * un code de récupération — vérifié.
 *
 * Route sous /api/login_check, donc couverte par le firewall "api_login"
 * (stateless, cf. security.yaml) comme /api/login_check lui-même : pas
 * d'utilisateur authentifié à ce stade au sens Symfony, seulement un jeton de
 * défi prouvant que le mot de passe a déjà été vérifié à l'étape précédente.
 */
final class ApiTwoFactorLoginController extends AbstractController
{
    #[Route('/api/login_check/2fa', name: 'api_login_check_two_factor', methods: ['POST'])]
    public function __invoke(
        Request $request,
        JWTService $jwt,
        JWTTokenManagerInterface $jwtManager,
        UserRepository $userRepository,
        TotpAuthenticatorInterface $totpAuthenticator,
        BackupCodeManager $backupCodeManager,
        EntityManagerInterface $entityManager,
        #[Autowire(service: 'limiter.api_login_2fa')]
        RateLimiterFactory $twoFactorLoginLimiter,
    ): Response {
        $data = json_decode($request->getContent(), true);
        $challengeToken = is_array($data) && is_string($data['challengeToken'] ?? null) ? $data['challengeToken'] : '';
        $code = is_array($data) && is_string($data['code'] ?? null) ? trim($data['code']) : '';

        if ('' === $challengeToken || '' === $code) {
            return new JsonResponse(['message' => 'Requête invalide : challengeToken et code sont requis.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $payload = $jwt->validate($challengeToken, 'api_2fa_challenge');
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['message' => 'Session de connexion expirée. Reconnectez-vous.'], Response::HTTP_UNAUTHORIZED);
        }

        $userId = $payload['user_id'] ?? null;
        $user = is_int($userId) ? $userRepository->find($userId) : null;

        if (!$user instanceof User || !$user->isTotpAuthenticationEnabled()) {
            return new JsonResponse(['message' => 'Compte introuvable.'], Response::HTTP_UNAUTHORIZED);
        }

        // Anti-bruteforce du code à 6 chiffres — même profil que TwoFactorAttemptListener
        // (connexion web) : le mot de passe est déjà connu à ce stade, seul le
        // second facteur reste à protéger contre un essai exhaustif.
        $limiter = $twoFactorLoginLimiter->create($user->getUserIdentifier());
        if (!$limiter->consume(1)->isAccepted()) {
            return new JsonResponse(['message' => 'Trop de tentatives. Patientez avant de réessayer.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Revérifié ici : le compte a pu être désactivé entre l'étape 1 (mot de
        // passe, vérifiée par UserChecker) et celle-ci — rien ne protège cet
        // intervalle autrement, le jeton de défi ne portant aucun statut de compte.
        if (!$user->isActive() || !$user->isVerified()) {
            return new JsonResponse(['message' => 'Compte indisponible.'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$totpAuthenticator->checkCode($user, $code)) {
            if (!$backupCodeManager->isValid($user, $code)) {
                return new JsonResponse(['message' => 'Code invalide.'], Response::HTTP_UNAUTHORIZED);
            }

            $backupCodeManager->invalidate($user, $code);
            $entityManager->flush();
        }

        $limiter->reset();

        return new JsonResponse(['token' => $jwtManager->create($user)]);
    }
}
