<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\TooManyRequestsException;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use App\Service\EmailManager;
use App\Service\JWTService;
use App\Service\PublicSubmissionThrottler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Réinitialisation de mot de passe pour le frontend Next.js (JWT, firewall
 * `api` stateless). Même mécanisme à usage unique que App\Controller\
 * SecurityController (routes web `/mot-de-passe-oublie` /
 * `/reinitialiser-mot-de-passe/{token}`) : token JWT dédié
 * (JWTService::generatePasswordResetToken) comparé à
 * User::passwordResetRequestedAt pour invalider tout lien supplanté par une
 * demande plus récente ou déjà consommé.
 */
#[Route('/api', name: 'api_password_reset_')]
final class PasswordResetController extends AbstractController
{
    #[Route('/forgot-password', name: 'request', methods: ['POST'])]
    public function requestReset(
        Request $request,
        UserRepository $userRepository,
        JWTService $jwt,
        EmailManager $emailManager,
        EntityManagerInterface $entityManager,
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
            if ($user instanceof User) {
                $now = new \DateTimeImmutable();
                $user->setPasswordResetRequestedAt($now);
                $entityManager->flush();

                $token = $jwt->generatePasswordResetToken($user->getId(), $now->getTimestamp());
                $emailManager->sendNow(
                    to: $user->getEmail(),
                    subject: 'Réinitialisation de votre mot de passe',
                    template: 'password_reset',
                    context: [
                        'user' => $user,
                        'token' => $token,
                        'fullName' => $user->getFullName(),
                    ],
                );
            }
        }

        // Même message que le compte existe ou non (anti-énumération, même
        // principe que App\Security\UserChecker pour le login).
        return $this->json([
            'detail' => "Si un compte existe pour cette adresse, un email contenant un lien de réinitialisation vient d'être envoyé.",
        ]);
    }

    #[Route('/reset-password', name: 'confirm', methods: ['POST'])]
    public function confirmReset(
        Request $request,
        JWTService $jwt,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['detail' => 'Corps de requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $token = (string) ($payload['token'] ?? '');
        $plainPassword = (string) ($payload['plainPassword'] ?? '');

        try {
            $tokenPayload = $jwt->validate($token, 'password_reset');
            $user = $userRepository->find($tokenPayload['user_id'] ?? 0);

            // Le lien n'est valide que s'il correspond à la dernière demande
            // enregistrée (usage unique + invalidation d'un lien supplanté).
            $requestedAt = $user instanceof User ? $user->getPasswordResetRequestedAt()?->getTimestamp() : null;
            if (!$user instanceof User || null === $requestedAt || $requestedAt !== ($tokenPayload['requested_at'] ?? null)) {
                throw new \InvalidArgumentException('Lien de réinitialisation déjà utilisé ou supplanté.');
            }
        } catch (\InvalidArgumentException) {
            return $this->json(['detail' => 'Ce lien de réinitialisation est invalide ou a expiré.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $violations = $validator->validate($plainPassword, [
            new Assert\Length(min: 8, max: 4096, minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.'),
            new Assert\Regex(
                pattern: '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).+$/',
                message: 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.',
            ),
        ]);
        if (\count($violations) > 0) {
            return $this->json(['detail' => $violations[0]->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // setPassword() invalide aussi passwordResetRequestedAt (usage unique).
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        $user->setPasswordChangedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $auditLogger->log(User::class, (int) $user->getId(), $user->getEmail(), 'password_reset_api');

        return $this->json(['detail' => 'Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.']);
    }
}
