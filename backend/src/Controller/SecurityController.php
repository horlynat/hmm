<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ForgotPasswordRequestType;
use App\Form\ResetPasswordFormType;
use App\Repository\UserRepository;
use App\Service\AccountLinkResolver;
use App\Service\AuditLogger;
use App\Service\EmailManager;
use App\Service\JWTService;
use App\Service\PublicSubmissionThrottler;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('target_path');
        // }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Demande de réinitialisation de mot de passe : même message affiché que
     * le compte existe ou non (anti-énumération, cf. App\Security\UserChecker
     * pour le même principe appliqué au login).
     */
    #[Route(path: '/mot-de-passe-oublie', name: 'forgot_password_request')]
    public function forgotPasswordRequest(
        Request $request,
        UserRepository $userRepository,
        JWTService $jwt,
        EmailManager $emailManager,
        EntityManagerInterface $entityManager,
        PublicSubmissionThrottler $throttler,
        AccountLinkResolver $accountLinkResolver,
    ): Response {
        $form = $this->createForm(ForgotPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $throttler->assertFormSubmissionAllowed();

            $email = (string) $form->get('email')->getData();
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
                        'fullName' => $user->getFullName(),
                        'resetUrl' => $accountLinkResolver->resolve(
                            $user,
                            'password_reset',
                            ['token' => $token],
                            '/reinitialiser-mot-de-passe/'.$token,
                        ),
                    ],
                );
            }

            $this->addFlash('success', "Si un compte existe pour cette adresse, un email contenant un lien de réinitialisation vient d'être envoyé.");

            return $this->redirectToRoute('login');
        }

        return $this->render('security/forgot_password_request.html.twig', ['form' => $form->createView()]);
    }

    #[Route(path: '/reinitialiser-mot-de-passe/{token}', name: 'password_reset')]
    public function resetPassword(
        string $token,
        Request $request,
        JWTService $jwt,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        try {
            $payload = $jwt->validate($token, 'password_reset');
            $user = $userRepository->find($payload['user_id'] ?? 0);

            // Le lien n'est valide que s'il correspond à la dernière demande
            // enregistrée pour ce compte : usage unique (remis à null après
            // un reset réussi, cf. User::setPassword()) et invalidation
            // automatique d'un lien supplanté par une demande plus récente.
            $requestedAt = $user instanceof User ? $user->getPasswordResetRequestedAt()?->getTimestamp() : null;
            if (!$user instanceof User || $requestedAt === null || $requestedAt !== ($payload['requested_at'] ?? null)) {
                throw new InvalidArgumentException('Lien de réinitialisation déjà utilisé ou supplanté.');
            }
        } catch (InvalidArgumentException) {
            $this->addFlash('danger', 'Ce lien de réinitialisation est invalide ou a expiré. Merci d’en demander un nouveau.');

            return $this->redirectToRoute('forgot_password_request');
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $confirmPassword = $form->get('confirmPassword')->getData();

            if ($plainPassword !== $confirmPassword) {
                $form->get('confirmPassword')->addError(new FormError('Les deux mots de passe ne correspondent pas.'));
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                $user->setPasswordChangedAt(new \DateTimeImmutable());
                $entityManager->flush();

                $auditLogger->log(User::class, (int) $user->getId(), $user->getEmail(), 'password_reset_web');
                $entityManager->flush();

                $this->addFlash('success', 'Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.');

                return $this->redirectToRoute('login');
            }
        }

        return $this->render('security/password_reset.html.twig', ['form' => $form->createView()]);
    }
}
