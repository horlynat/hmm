<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileType;
use App\Security\Voter\UserVoter;
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
 * Page de profil (self-service) : accessible dès ROLE_USER, mais uniquement pour
 * soi-même. Un administrateur peut consulter/modifier le profil d'un autre
 * utilisateur (UserVoter::VIEW / EDIT / RESET_PASSWORD, mêmes règles que les
 * contrôleurs Admin*), un simple client ne le peut jamais.
 */
#[Route('/profile', name: 'profile_', methods: ['GET', 'POST'])]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/{id}', name: 'read', methods: ['GET'])]
    public function read(
        User $user,
        ProfileCompletionService $completionService,
        GeolocationService $geolocationService
    ): Response {
        $this->denyAccessUnlessSelfOrGranted(UserVoter::VIEW, $user);

        $completionPercentage = $completionService->calculateCompletionPercentage($user);

        // Récupérer la localisation basée sur l'IP
        $location = null;
        if ($user->getLastIp()) {
            $location = $geolocationService->getLocationFromIp($user->getLastIp());
        }

        return $this->render('profile/profile.html.twig', [
            'user' => $user,
            'completionPercentage' => $completionPercentage,
            'location' => $location,
        ]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'])]
    public function update(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $this->denyAccessUnlessSelfOrGranted(UserVoter::EDIT, $user);

        $form = $this->createForm(ProfileType::class, $user);
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
                    return $this->render('profile/update.html.twig', [
                        'user' => $user,
                        'form' => $form->createView(),
                    ]);
                }
            }

            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('profile_read', ['id' => $user->getId()]);
        }

        return $this->render('profile/update.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/change-password', name: 'change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $this->denyAccessUnlessSelfOrGranted(UserVoter::RESET_PASSWORD, $user);

        $form = $this->createForm(ProfileType::class, $user, [
            'validation_groups' => ['change_password'],
        ]);
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
                return $this->redirectToRoute('profile_read', ['id' => $user->getId()]);
            } else {
                $form->get('plainPassword')->addError(new \Symfony\Component\Form\FormError('Les mots de passe ne correspondent pas.'));
            }
        }

        return $this->render('profile/_change_password.html.twig', [
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