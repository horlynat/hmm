<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileType;
use App\Form\ResetPasswordFormType;
use App\Repository\UserSessionRepository;
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
 * Page de profil de l'espace membre (self-service uniquement) : un client ou
 * un collaborateur/freelance ne gère jamais que son propre compte ici, jamais
 * celui d'un autre (contrairement au back-office, il n'existe pas de notion
 * d'admin consultant la fiche d'un tiers dans cet espace — pas de paramètre
 * {id}, toujours le compte actuellement connecté). Rendue avec le gabarit
 * de l'espace membre (member/base.html.twig, aside collaborateur/freelance),
 * jamais l'en-tête ni l'aside admin.
 *
 * Distinct de App\Controller\Admin\AdminProfileController (/admin/profil),
 * qui gère le même besoin côté back-office, avec en plus la consultation/
 * édition d'un autre compte par un administrateur.
 */
#[Route('/user/profil', name: 'member_profile_', methods: ['GET', 'POST'])]
#[IsGranted('ROLE_USER')]
class MemberProfileController extends AbstractController
{
    #[Route('', name: 'read', methods: ['GET'])]
    public function read(
        ProfileCompletionService $completionService,
        GeolocationService $geolocationService,
        DeviceParser $deviceParser,
        UserSessionRepository $userSessionRepository,
        LiveSessionStateResolver $liveSessionStateResolver,
        Request $request,
    ): Response {
        $user = $this->currentUser();

        $completionPercentage = $completionService->calculateCompletionPercentage($user);

        // Récupérer la localisation basée sur l'IP
        $location = null;
        if ($user->getLastIp()) {
            $location = $geolocationService->getLocationFromIp($user->getLastIp());
        }

        return $this->render('member/profile/read.html.twig', [
            'user' => $user,
            'completionPercentage' => $completionPercentage,
            'location' => $location,
            'deviceInfo' => $deviceParser->parse($user->getLastDevice()),
            'mySessions' => $this->buildMySessions($user, $userSessionRepository, $liveSessionStateResolver, $deviceParser),
            'currentSessionId' => $request->getSession()->getId(),
        ]);
    }

    #[Route('/modifier', name: 'update', methods: ['GET', 'POST'])]
    public function update(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $user = $this->currentUser();

        $form = $this->createForm(ProfileType::class, $user, [
            'isCollaborator' => \in_array('ROLE_EDITOR', $user->getRoles(), true),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $confirmPassword = $form->get('confirmPassword')->getData();

            if (!empty($plainPassword)) {
                if ($plainPassword === $confirmPassword) {
                    $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                    $user->setPasswordChangedAt(new \DateTimeImmutable());
                } else {
                    $form->get('plainPassword')->addError(new \Symfony\Component\Form\FormError('Les mots de passe ne correspondent pas.'));

                    return $this->render('member/profile/update.html.twig', [
                        'user' => $user,
                        'form' => $form->createView(),
                    ]);
                }
            }

            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Profil mis à jour avec succès !');

            return $this->redirectToRoute('member_profile_read');
        }

        return $this->render('member/profile/update.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/mot-de-passe', name: 'change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $user = $this->currentUser();

        // Formulaire dédié (2 champs) plutôt que ProfileType + validation_groups :
        // ProfileType a 10 champs mappés sur le même User, donc form_end(form)
        // (sans render_rest:false) auto-rendait TOUS les autres champs
        // (nom/email/téléphone/rôles système...) sur cette page censée ne
        // montrer que le mot de passe.
        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $confirmPassword = $form->get('confirmPassword')->getData();

            if ($plainPassword === $confirmPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
                $user->setPasswordChangedAt(new \DateTimeImmutable());
                $entityManager->flush();

                $this->addFlash('success', 'Mot de passe changé avec succès !');

                return $this->redirectToRoute('member_profile_read');
            }
            $form->get('plainPassword')->addError(new \Symfony\Component\Form\FormError('Les mots de passe ne correspondent pas.'));
        }

        return $this->render('member/profile/change_password.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * #[IsGranted('ROLE_USER')] au niveau classe garantit un utilisateur
     * authentifié avant l'entrée dans toute action ; ce point unique évite de
     * répéter le contrôle de type (Symfony\Bundle\...\getUser() reste
     * ?UserInterface au niveau des types).
     */
    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    /**
     * Alimente le bloc "Mes appareils connectés" (partagé avec
     * AdminProfileController::read() — cf. _partials/_my_sessions.html.twig).
     * Uniquement les sessions ACTIVES ou EXPIRÉES : une session déjà "terminée"
     * n'a rien à révoquer, l'afficher n'apporterait rien à l'utilisateur.
     *
     * @return list<array{session: \App\Entity\UserSession, state: string, device: array{type: string, brand: ?string, model: ?string, os: ?string, browser: ?string, label: string, isBot: bool}}>
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

        // Plus récente en premier — "mes appareils" se lit naturellement du plus
        // au moins récent, contrairement à la liste admin (triée par ancienneté
        // pour l'éviction de capacité).
        return array_reverse($entries);
    }
}
