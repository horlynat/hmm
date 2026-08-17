<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\ProfileType;
use App\Form\ResetPasswordFormType;
use App\Security\Voter\UserVoter;
use App\Service\DeviceParser;
use App\Service\GeolocationService;
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
    ): Response {
        $this->denyAccessUnlessSelfOrGranted(UserVoter::VIEW, $user);

        $completionPercentage = $completionService->calculateCompletionPercentage($user);

        // Récupérer la localisation basée sur l'IP
        $location = null;
        if ($user->getLastIp()) {
            $location = $geolocationService->getLocationFromIp($user->getLastIp());
        }

        return $this->render('admin/profile/read.html.twig', [
            'user' => $user,
            'completionPercentage' => $completionPercentage,
            'location' => $location,
            'deviceInfo' => $deviceParser->parse($user->getLastDevice()),
        ]);
    }

    #[Route('/{id}/update', name: 'update', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function update(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $this->denyAccessUnlessSelfOrGranted(UserVoter::EDIT, $user);

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

                    return $this->render('admin/profile/update.html.twig', [
                        'user' => $user,
                        'form' => $form->createView(),
                    ]);
                }
            }

            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Profil mis à jour avec succès !');

            return $this->redirectToRoute('admin_profile_read', ['id' => $user->getId()]);
        }

        return $this->render('admin/profile/update.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/change-password', name: 'change_password', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $this->denyAccessUnlessSelfOrGranted(UserVoter::RESET_PASSWORD, $user);

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

                return $this->redirectToRoute('admin_profile_read', ['id' => $user->getId()]);
            }
            $form->get('plainPassword')->addError(new \Symfony\Component\Form\FormError('Les mots de passe ne correspondent pas.'));
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
