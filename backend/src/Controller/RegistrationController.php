<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\NotificationPriorityEnum;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\AdminAlertNotifier;
use App\Service\EmailManager;
use App\Service\JWTService;
use App\Service\PublicSubmissionThrottler;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    public function __construct(
        private JWTService $jwt,
        private EmailManager $emailManager, // ✅ Un seul service, deux méthodes
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/register', name: 'register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, AdminAlertNotifier $adminAlertNotifier, PublicSubmissionThrottler $throttler): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $throttler->assertRegistrationAllowed();

            $user->setPassword($userPasswordHasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $user->setRoles(['ROLE_USER']);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $adminAlertNotifier->alert(
                NotificationPriorityEnum::LOW,
                'Nouvelle inscription',
                sprintf('%s <%s> vient de créer un compte.', $user->getFullName() ?? $user->getEmail(), $user->getEmail()),
            );

            $token = $this->jwt->generateEmailVerificationToken($user->getId());

            // ✅ sendNow() — synchrone car le token JWT a une durée de vie limitée
            $this->emailManager->sendNow(
                to: $user->getEmail(),
                subject: 'Confirmez votre adresse email',
                template: 'confirmation_email',
                context: [
                    'user' => $user,
                    'token' => $token,
                    'fullName' => $user->getFullName(),
                ]
            );

            $this->addFlash('success', 'Inscription réussie ! Vérifiez vos emails.');

            return $this->redirectToRoute('profile_read', ['id' => $user->getId()]);
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/verif/{token}', name: 'verify_user')]
    public function verifyUser(string $token, UserRepository $userRepository): Response
    {
        try {
            $payload = $this->jwt->validate($token, 'email_verification');
            $user = $userRepository->find($payload['user_id']);

            if (!$user) {
                throw new \InvalidArgumentException('Utilisateur introuvable.');
            }

            if ($user->isVerified()) {
                $this->addFlash('warning', 'Votre compte est déjà activé.');

                return $this->redirectToRoute('profile_read');
            }

            $user->setIsVerified(true);
            $this->entityManager->flush();

            // ✅ sendAsync() — non-critique, pas de token expirant
            $this->emailManager->sendAsync(
                to: $user->getEmail(),
                subject: 'Votre compte est activé',
                template: 'email_verified',
                context: [
                    'fullName' => $user->getFullName(),
                    'user' => $user, // ✅ Passez l'objet user complet au template
                ]
            );

            $this->addFlash('success', 'Votre compte a été activé !');

            return $this->redirectToRoute('profile_read');
        } catch (\InvalidArgumentException $e) {
            // JWTService::validate() ne lève que InvalidArgumentException (jamais les
            // classes typées JWTExpiredException/JWTInvalidSignatureException/
            // JWTInvalidFormatException) : un seul message générique est affiché au
            // client pour ne pas exposer la raison exacte (signature/format/expiration)
            // d'un échec de validation ; le détail reste en log.
            $this->logger->info('Échec de vérification email.', ['reason' => $e->getMessage()]);
            $this->addFlash('danger', 'Le lien de vérification est invalide ou a expiré. Veuillez en demander un nouveau.');

            return $this->redirectToRoute('resend_verif');
        }
    }

    #[Route('/renvoiverif', name: 'resend_verif')]
    public function resendVerif(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        if ($user->isVerified()) {
            $this->addFlash('warning', 'Votre compte est déjà activé.');

            return $this->redirectToRoute('profile_read');
        }

        $token = $this->jwt->generateEmailVerificationToken($user->getId());

        // ✅ sendNow() — même raison : token JWT sensible au temps
        $this->emailManager->sendNow(
            to: $user->getEmail(),
            subject: 'Confirmez votre adresse email',
            template: 'confirmation_email',
            context: [
                'user' => $user,
                'token' => $token,
                'fullName' => $user->getFullName(),
            ]
        );

        $this->addFlash('success', 'Un nouveau lien de vérification vous a été envoyé.');

        return $this->redirectToRoute('profile_read');
    }
}
