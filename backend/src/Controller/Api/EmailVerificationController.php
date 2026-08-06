<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\TooManyRequestsException;
use App\Repository\UserRepository;
use App\Service\AccountLinkResolver;
use App\Service\EmailManager;
use App\Service\JWTService;
use App\Service\PublicSubmissionThrottler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vérification d'email pour le frontend Next.js, entièrement accessible SANS
 * être authentifié : App\Security\UserChecker bloque la connexion (web ET
 * JWT, cf. security.yaml) tant que User::isVerified() est faux, donc un
 * client/freelance dont le lien initial (envoyé à l'inscription, valable 24h
 * — cf. JWTService::EMAIL_VERIFICATION_VALIDITY) a expiré ou s'est perdu ne
 * pouvait jusqu'ici en obtenir un nouveau qu'en étant déjà connecté, ce qui
 * est impossible par construction pour un compte non vérifié.
 */
#[Route('/api', name: 'api_email_verification_')]
final class EmailVerificationController extends AbstractController
{
    public function __construct(private readonly AccountLinkResolver $accountLinkResolver)
    {
    }

    /**
     * Anti-énumération : réponse identique que le compte existe, soit déjà
     * vérifié, ou non — même principe que
     * App\Controller\Api\PasswordResetController::requestReset.
     */
    #[Route('/resend-verification-email', name: 'resend', methods: ['POST'])]
    public function resend(
        Request $request,
        UserRepository $userRepository,
        JWTService $jwt,
        EmailManager $emailManager,
        PublicSubmissionThrottler $throttler,
    ): JsonResponse {
        try {
            $throttler->assertFormSubmissionAllowed();
        } catch (TooManyRequestsException $e) {
            return $this->json(['detail' => $e->getMessage()], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $payload = json_decode($request->getContent(), true);
        $email = \is_array($payload) ? trim((string) ($payload['email'] ?? '')) : '';

        if ('' !== $email) {
            $user = $userRepository->findOneBy(['email' => $email]);
            if ($user instanceof User && !$user->isVerified()) {
                $token = $jwt->generateEmailVerificationToken($user->getId());
                $emailManager->sendNow(
                    to: $user->getEmail(),
                    subject: 'Confirmez votre adresse email',
                    template: 'confirmation_email',
                    context: [
                        'user' => $user,
                        'fullName' => $user->getFullName(),
                        'verifyUrl' => $this->accountLinkResolver->resolve(
                            $user,
                            'verify_user',
                            ['token' => $token],
                            '/verification-email/'.$token,
                        ),
                    ],
                );
            }
        }

        return $this->json([
            'detail' => "Si un compte existe pour cette adresse et n'est pas encore vérifié, un nouveau lien de confirmation vient d'être envoyé.",
        ]);
    }

    /**
     * Consomme le token de vérification depuis le frontend — pendant que
     * App\Controller\RegistrationController::verifyUser() reste le point
     * d'entrée pour un lien cliqué depuis un contexte back-office (compte
     * ROLE_ADMIN, cf. AccountLinkResolver), les liens envoyés aux clients et
     * freelances pointent désormais ici via /verification-email/{token} côté
     * Next.js.
     */
    #[Route('/verify-email', name: 'confirm', methods: ['POST'])]
    public function confirm(
        Request $request,
        JWTService $jwt,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        EmailManager $emailManager,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        $token = \is_array($payload) ? (string) ($payload['token'] ?? '') : '';

        try {
            $tokenPayload = $jwt->validate($token, 'email_verification');
            $user = $userRepository->find($tokenPayload['user_id'] ?? 0);
            if (!$user instanceof User) {
                throw new \InvalidArgumentException('Utilisateur introuvable.');
            }
        } catch (\InvalidArgumentException) {
            return $this->json(['detail' => 'Ce lien de vérification est invalide ou a expiré.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($user->isVerified()) {
            return $this->json(['detail' => 'Votre compte était déjà activé.']);
        }

        $user->setIsVerified(true);
        $entityManager->flush();

        $emailManager->sendAsync(
            to: $user->getEmail(),
            subject: 'Votre compte est activé',
            template: 'email_verified',
            context: [
                'fullName' => $user->getFullName(),
                'user' => $user,
                'accountUrl' => $this->accountLinkResolver->resolve($user, 'profile_read', ['id' => $user->getId()], '/connexion'),
            ],
        );

        return $this->json(['detail' => 'Votre compte a été activé.']);
    }
}
