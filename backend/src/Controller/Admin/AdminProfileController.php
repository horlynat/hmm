<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\UserSession;
use App\Form\ProfileType;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordFormType;
use App\Security\PasswordConfirmationGuard;
use App\Service\UserSessionRevoker;
use App\Repository\UserSessionRepository;
use App\Security\Voter\UserVoter;
use App\Service\DeviceParser;
use App\Service\GeolocationService;
use App\Service\LiveSessionStateResolver;
use App\Service\ProfileCompletionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Page de profil côté back-office : gère à la fois le profil personnel d'un
 * administrateur (id = son propre compte) et la fiche compte d'un autre
 * utilisateur consultée/éditée depuis l'administration — Utilisateurs,
 * Collaborateurs, Administrateurs (UserVoter::VIEW / EDIT / RESET_PASSWORD,
 * mêmes règles que les contrôleurs Admin*). Rendue avec le gabarit
 * back-office (base.html.twig, en-tête + aside admin).
 *
 * Distinct de App\Controller\MemberProfileController (/user/profil), qui gère
 * le même besoin côté espace membre (client/collaborateur), self-service
 * uniquement, avec le gabarit member/base.html.twig — jamais l'aside admin.
 */
#[Route('/admin/profil', name: 'admin_profile_', methods: ['GET', 'POST'])]
#[IsGranted('ROLE_ADMIN')]
class AdminProfileController extends AbstractController
{
    #[Route('/{id}', name: 'read', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function read(
        User $user,
        ProfileCompletionService $completionService,
        GeolocationService $geolocationService,
        DeviceParser $deviceParser,
        UserSessionRepository $userSessionRepository,
        LiveSessionStateResolver $liveSessionStateResolver,
        Request $request,
    ): Response {
        $this->denyAccessUnlessSelfOrGranted(UserVoter::VIEW, $user);

        $completionPercentage = $completionService->calculateCompletionPercentage($user);

        // Récupérer la localisation basée sur l'IP
        $location = null;
        if ($user->getLastIp()) {
            $location = $geolocationService->getLocationFromIp($user->getLastIp());
        }

        // "Mes appareils connectés" n'a de sens que sur SON PROPRE profil — un
        // admin qui consulte la fiche d'un autre administrateur voit déjà le
        // bouton "Déconnecter partout" (admin/admins/read.html.twig), qui couvre
        // ce besoin-là sans exposer le détail par appareil d'un tiers ici.
        $mySessions = null;
        if ($user === $this->getUser()) {
            $mySessions = $this->buildMySessions($user, $userSessionRepository, $liveSessionStateResolver, $deviceParser);
        }

        return $this->render('admin/profile/read.html.twig', [
            'user' => $user,
            'completionPercentage' => $completionPercentage,
            'location' => $location,
            'deviceInfo' => $deviceParser->parse($user->getLastDevice()),
            'mySessions' => $mySessions,
            'currentSessionId' => $request->getSession()->getId(),
        ]);
    }

    /**
     * Même logique que MemberProfileController::buildMySessions() — dupliquée
     * volontairement (deux petits contrôleurs indépendants plutôt qu'un
     * service partagé pour ~15 lignes, cf. principe déjà appliqué à
     * SessionCsvExporter/FinanceCsvExporter dans ce projet).
     *
     * @return list<array{session: UserSession, state: string, device: array{type: string, brand: ?string, model: ?string, os: ?string, browser: ?string, label: string, isBot: bool}}>
     */
    private function buildMySessions(User $user, UserSessionRepository $userSessionRepository, LiveSessionStateResolver $liveSessionStateResolver, DeviceParser $deviceParser): array
    {
        $liveSessions = $liveSessionStateResolver->resolveAll();

        $entries = [];
        foreach ($userSessionRepository->findByUserOrderedByCreatedAt($user) as $userSession) {
            $live = $liveSessions[$userSession->getSessionId()] ?? null;
            $state = match (true) {
                null === $live => 'ended',
                $live['active'] => 'active',
                default => 'expired',
            };

            if ('ended' === $state) {
                continue;
            }

            $entries[] = [
                'session' => $userSession,
                'state' => $state,
                'device' => $deviceParser->parse($userSession->getUserAgent()),
            ];
        }

        return array_reverse($entries);
    }

    /**
     * Auto-édition (mot de passe ACTUEL exigé, P1 #7 — PasswordConfirmationGuard)
     * OU administration d'un AUTRE compte par un admin (UserVoter::EDIT) : ce
     * second cas est une action administrative déjà autorisée par le voter,
     * AUCUNE re-saisie n'est demandée (l'admin ne connaît pas et ne doit pas
     * avoir besoin de connaître le mot de passe du compte qu'il édite).
     */
    #[Route('/{id}/update', name: 'update', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function update(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        PasswordConfirmationGuard $passwordConfirmationGuard,
        UserSessionRevoker $userSessionRevoker,
    ): Response {
        $this->denyAccessUnlessSelfOrGranted(UserVoter::EDIT, $user);
        $isSelf = $user === $this->getUser();

        $form = $this->createForm(ProfileType::class, $user, [
            'isCollaborator' => \in_array('ROLE_EDITOR', $user->getRoles(), true),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $confirmPassword = $form->get('confirmPassword')->getData();

            if (!empty($plainPassword)) {
                if ($plainPassword !== $confirmPassword) {
                    $form->get('plainPassword')->addError(new \Symfony\Component\Form\FormError('Les mots de passe ne correspondent pas.'));
                } elseif ($isSelf) {
                    $check = $passwordConfirmationGuard->check($user, $form->get('currentPassword')->getData());
                    if (PasswordConfirmationGuard::THROTTLED === $check) {
                        $form->get('currentPassword')->addError(new \Symfony\Component\Form\FormError('Trop de tentatives. Patientez avant de réessayer.'));
                    } elseif (PasswordConfirmationGuard::OK !== $check) {
                        $form->get('currentPassword')->addError(new \Symfony\Component\Form\FormError('Mot de passe actuel incorrect.'));
                    } else {
                        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                        $user->setPasswordChangedAt(new \DateTimeImmutable());
                        $userSessionRevoker->revokeAll($user);
                    }
                } else {
                    // Action administrative sur le compte d'un tiers : pas de
                    // re-saisie, comportement inchangé.
                    $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                    $user->setPasswordChangedAt(new \DateTimeImmutable());
                    $userSessionRevoker->revokeAll($user);
                }
            }

            if (!$form->get('plainPassword')->getErrors()->count() && !$form->get('currentPassword')->getErrors()->count()) {
                $user->setUpdatedAt(new \DateTimeImmutable());
                $entityManager->flush();

                $this->addFlash('success', 'Profil mis à jour avec succès !');

                return $this->redirectToRoute('admin_profile_read', ['id' => $user->getId()]);
            }
        }

        return $this->render('admin/profile/update.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Auto-édition : ChangePasswordFormType (mot de passe ACTUEL exigé,
     * P1 #7). Admin réinitialisant le mot de passe d'un AUTRE compte :
     * ResetPasswordFormType inchangé (action administrative déjà autorisée
     * par UserVoter::RESET_PASSWORD, l'admin ne connaît pas le mot de passe
     * du tiers par définition).
     */
    #[Route('/{id}/change-password', name: 'change_password', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        PasswordConfirmationGuard $passwordConfirmationGuard,
        UserSessionRevoker $userSessionRevoker,
    ): Response {
        $this->denyAccessUnlessSelfOrGranted(UserVoter::RESET_PASSWORD, $user);
        $isSelf = $user === $this->getUser();

        $form = $this->createForm($isSelf ? ChangePasswordFormType::class : ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $confirmPassword = $form->get('confirmPassword')->getData();

            if ($plainPassword !== $confirmPassword) {
                $form->get('plainPassword')->addError(new \Symfony\Component\Form\FormError('Les mots de passe ne correspondent pas.'));
            } elseif ($isSelf) {
                $check = $passwordConfirmationGuard->check($user, $form->get('currentPassword')->getData());
                if (PasswordConfirmationGuard::THROTTLED === $check) {
                    $form->get('currentPassword')->addError(new \Symfony\Component\Form\FormError('Trop de tentatives. Patientez avant de réessayer.'));
                } elseif (PasswordConfirmationGuard::OK !== $check) {
                    $form->get('currentPassword')->addError(new \Symfony\Component\Form\FormError('Mot de passe actuel incorrect.'));
                } else {
                    $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                    $user->setPasswordChangedAt(new \DateTimeImmutable());
                    $userSessionRevoker->revokeAll($user);
                    $entityManager->flush();

                    $this->addFlash('success', 'Mot de passe changé avec succès !');

                    return $this->redirectToRoute('admin_profile_read', ['id' => $user->getId()]);
                }
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                $user->setPasswordChangedAt(new \DateTimeImmutable());
                $userSessionRevoker->revokeAll($user);
                $entityManager->flush();

                $this->addFlash('success', 'Mot de passe changé avec succès !');

                return $this->redirectToRoute('admin_profile_read', ['id' => $user->getId()]);
            }
        }

        return $this->render('admin/profile/change_password.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Autorise l'action si $user est le compte connecté, sinon retombe sur
     * $attribute (UserVoter), qui gère les règles admin (self-service exclu :
     * un simple ROLE_USER ne passe jamais ce second cas, seul un admin le peut).
     */
    private function denyAccessUnlessSelfOrGranted(string $attribute, User $user): void
    {
        $currentUser = $this->getUser();

        if ($currentUser instanceof User && $user->getId() === $currentUser->getId()) {
            return;
        }

        $this->denyAccessUnlessGranted($attribute, $user);
    }
}
