<?php

namespace App\Controller;

use App\Entity\User;
use App\EventSubscriber\SpaceTrackerSubscriber;
use App\Form\EmailChangeRequestType;
use App\Repository\UserRepository;
use App\Security\PasswordConfirmationGuard;
use App\Service\AccountLinkResolver;
use App\Service\EmailManager;
use App\Service\JWTService;
use App\Service\MailLinkGenerator;
use App\Service\UserSessionRevoker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Changement d'e-mail self-service EN DEUX TEMPS (P1 #7), partagé entre
 * l'espace membre et le back-office (même principe que TwoFactorController :
 * contrôleur unique, gabarit choisi selon le dernier espace parcouru, cf.
 * layout()). Jamais pour un administrateur éditant le compte d'un TIERS
 * (AdminProfileController garde son comportement direct pour ce cas précis,
 * hors du périmètre de cette demande — cf. son docblock).
 *
 * Flux :
 * 1. requestChange() : mot de passe actuel + nouvelle adresse -> stockée en
 *    attente (User::$pendingEmail), jamais écrite dans `email` tout de suite.
 *    Un e-mail de confirmation part vers la NOUVELLE adresse (seul moyen de
 *    prouver qu'elle appartient bien au titulaire du compte) ; une
 *    notification part aussi vers l'ANCIENNE (alerte si ce changement n'est
 *    pas de son fait — l'ancienne adresse reste active jusqu'à confirmation).
 * 2. confirm() : clic depuis la nouvelle adresse -> email réellement changé,
 *    toutes les sessions/jetons existants révoqués (changer d'identifiant de
 *    connexion est aussi sensible qu'un changement de mot de passe).
 */
#[Route('/profile/email', name: 'profile_email_')]
class ProfileEmailChangeController extends AbstractController
{
    public function __construct(
        private readonly AccountLinkResolver $accountLinkResolver,
        private readonly RequestStack $requestStack,
        private readonly MailLinkGenerator $mailLinkGenerator,
    ) {
    }

    #[Route('', name: 'request', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function requestChange(
        \Symfony\Component\HttpFoundation\Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        PasswordConfirmationGuard $passwordConfirmationGuard,
        JWTService $jwt,
        EmailManager $emailManager,
        #[\Symfony\Component\DependencyInjection\Attribute\Target('email_change_request')]
        \Symfony\Component\RateLimiter\RateLimiterFactoryInterface $emailChangeRequestLimiter,
    ): Response {
        $user = $this->currentUser();

        $form = $this->createForm(EmailChangeRequestType::class);
        $form->handleRequest($request);
        $error = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $newEmail = mb_strtolower(trim((string) $form->get('newEmail')->getData()));
            $currentPassword = (string) $form->get('currentPassword')->getData();

            $check = $passwordConfirmationGuard->check($user, $currentPassword);
            if (PasswordConfirmationGuard::THROTTLED === $check) {
                $error = 'Trop de tentatives. Patientez avant de réessayer.';
            } elseif (PasswordConfirmationGuard::OK !== $check) {
                $error = 'Mot de passe actuel incorrect.';
            } elseif ($newEmail === mb_strtolower($user->getEmail())) {
                $error = "C'est déjà votre adresse actuelle.";
            } elseif (null !== $userRepository->findOneBy(['email' => $newEmail])) {
                // Message neutre : ne confirme ni n'infirme explicitement qu'un
                // AUTRE compte existe avec cette adresse (l'ancienne adresse,
                // elle, continue de recevoir la notification ci-dessous dans
                // tous les autres cas — une tentative sur un e-mail déjà pris
                // par un tiers ne révèle rien à ce tiers).
                $error = 'Cette adresse ne peut pas être utilisée.';
            } elseif (!$emailChangeRequestLimiter->create('user_'.$user->getId())->consume()->isAccepted()) {
                // Contre-audit B2 : chaque demande envoie un mail vers une
                // adresse choisie par le demandeur — au plus 3 par heure.
                $error = 'Trop de demandes de changement d\'adresse. Réessayez dans une heure.';
            } else {
                $now = new \DateTimeImmutable();
                $user->requestEmailChange($newEmail, $now);
                $entityManager->flush();

                $token = $jwt->generateEmailChangeToken((int) $user->getId(), $now->getTimestamp());
                // Toujours une route de CE backend (jamais la base de
                // routage AccountLinkResolver, qui bascule vers le frontend
                // Next.js pour un ROLE_USER — cette page n'y existe pas, ce
                // flux est purement web/Symfony, membre comme back-office).
                $confirmUrl = $this->mailLinkGenerator->backOfficeUrl('profile_email_confirm', ['token' => $token]);

                $emailManager->sendNow(
                    to: $newEmail,
                    subject: 'Confirmez votre nouvelle adresse e-mail',
                    template: 'email_change_confirm',
                    context: ['user' => $user, 'fullName' => $user->getFullName(), 'confirmUrl' => $confirmUrl],
                );
                // Asynchrone : purement informatif, aucun jeton à durée de vie
                // courte dans ce message, pas de délai critique.
                $emailManager->sendAsync(
                    to: $user->getEmail(),
                    subject: "Demande de changement d'adresse e-mail",
                    template: 'email_change_notice',
                    context: ['user' => $user, 'fullName' => $user->getFullName(), 'newEmail' => $newEmail],
                );

                $this->addFlash('success', sprintf('Un e-mail de confirmation a été envoyé à %s. Votre adresse actuelle reste active jusqu\'à ce que vous cliquiez sur le lien.', $newEmail));

                return $this->redirectToRoute('profile_email_request');
            }
        }

        return $this->render('profile/email_change_request.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'error' => $error,
            'layout' => $this->layout(),
        ]);
    }

    /**
     * SANS #[IsGranted] : cliqué depuis un client mail, pas forcément dans le
     * même navigateur/appareil que la session qui a fait la demande — comme
     * la réinitialisation de mot de passe, le jeton lui-même porte
     * l'identité du compte (route PUBLIC_ACCESS, cf. security.yaml), jamais
     * `$this->getUser()`.
     */
    #[Route('/confirmer/{token}', name: 'confirm', methods: ['GET'])]
    public function confirm(
        string $token,
        JWTService $jwt,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserSessionRevoker $userSessionRevoker,
        \App\Service\AuditLogger $auditLogger,
    ): Response {
        try {
            $payload = $jwt->validate($token, 'email_change');
            $user = $userRepository->find($payload['user_id'] ?? 0);
            if (!$user instanceof User) {
                throw new \InvalidArgumentException();
            }
        } catch (\InvalidArgumentException) {
            $this->addFlash('danger', 'Ce lien de confirmation est invalide ou a expiré. Merci de refaire la demande.');

            return $this->redirectToRoute('login');
        }

        // Le jeton doit correspondre à la dernière demande enregistrée (usage
        // unique + invalidation d'une demande supplantée par une plus
        // récente) — même mécanique que le reset de mot de passe.
        $requestedAt = $user->getEmailChangeRequestedAt()?->getTimestamp();
        if (null === $user->getPendingEmail()
            || null === $requestedAt
            || $requestedAt !== ($payload['requested_at'] ?? null)
        ) {
            $this->addFlash('danger', 'Ce lien de confirmation est invalide, a expiré, ou a déjà été utilisé.');

            return $this->redirectToRoute('login');
        }

        $newEmail = $user->getPendingEmail();

        // Revérifié à l'instant de la confirmation (course possible depuis la
        // demande initiale : un autre compte a pu entre-temps prendre cette
        // adresse).
        $existing = $userRepository->findOneBy(['email' => $newEmail]);
        if (null !== $existing && $existing !== $user) {
            $user->clearPendingEmailChange();
            $entityManager->flush();
            $this->addFlash('danger', "Cette adresse n'est plus disponible. Merci de refaire la demande avec une autre.");

            return $this->redirectToRoute('login');
        }

        $oldEmail = $user->getEmail();
        // Changer l'identifiant de connexion est aussi sensible qu'un
        // changement de mot de passe : toute session/JWT antérieur tombe.
        // AVANT setEmail() (contre-audit B2, M2) : les remember-me sont
        // purgés par adresse — après le changement, ceux de l'ancienne
        // adresse survivaient et restaient rejouables contre quiconque la
        // reprendrait.
        $userSessionRevoker->revokeAll($user);
        $user->setEmail($newEmail); // clears pendingEmail/emailChangeRequestedAt itself
        $entityManager->flush();

        $auditLogger->log(User::class, (int) $user->getId(), $newEmail, 'email_changed', sprintf('Ancienne adresse : %s.', $oldEmail));

        $this->addFlash('success', 'Votre adresse e-mail a été mise à jour. Merci de vous reconnecter.');

        return $this->redirectToRoute('login');
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    /** Même logique que TwoFactorController::layout() (cf. son docblock). */
    private function layout(): string
    {
        $space = $this->requestStack->getSession()->get(SpaceTrackerSubscriber::SESSION_KEY);

        $isBackOffice = match ($space) {
            SpaceTrackerSubscriber::SPACE_ADMIN => true,
            SpaceTrackerSubscriber::SPACE_MEMBER => false,
            default => $this->accountLinkResolver->isBackOfficeUser($this->currentUser()),
        };

        return $isBackOffice ? 'base.html.twig' : 'member/base.html.twig';
    }
}
