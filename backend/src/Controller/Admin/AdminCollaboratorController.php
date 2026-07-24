<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Security\Voter\UserVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur pour la gestion des comptes collaborateurs (pros/freelances associés à des projets).
 *
 * 🔒 Sécurité :
 * - Réservé exclusivement aux administrateurs (ROLE_ADMIN).
 * - Le rôle ROLE_EDITOR est normalement attribué/retiré automatiquement
 *   lors de l'ajout/retrait d'un utilisateur comme collaborateur d'un projet
 *   (voir User::addCollaboratingProject / removeCollaboratingProject).
 */
#[Route('/admin/collaborators', name: 'admin_collaborator_')]
final class AdminCollaboratorController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/collaborator/index.html.twig', [
            'collaborators' => $this->userRepository->findCollaborators(),
            'candidatesCount' => count($this->userRepository->findFreelanceCandidates()),
        ]);
    }

    // =========================================================================
    // 📌 CANDIDATURES FREELANCE EN ATTENTE (inscription publique, pas encore
    //    promues ROLE_EDITOR) — la promotion se fait via read()/update() ci-
    //    dessous, communs à tout compte User quel que soit son rôle actuel.
    // =========================================================================

    #[Route('/candidates', name: 'candidates', methods: ['GET'])]
    public function candidates(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/collaborator/candidates.html.twig', [
            'candidates' => $this->userRepository->findFreelanceCandidates(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = new User();
        $user->setRoles(['ROLE_EDITOR']);
        $user->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePassword($user, $form);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Le compte collaborateur #%d a été créé avec succès.', $user->getId()));
            return $this->redirectToRoute('admin_collaborator_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/collaborator/create.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(User $user): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $user);

        return $this->render('admin/collaborator/read.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function update(Request $request, User $user): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $user);

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePassword($user, $form);
            $user->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Le compte collaborateur #%d a été mis à jour avec succès.', $user->getId()));
            return $this->redirectToRoute('admin_collaborator_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/collaborator/update.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, User $user): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::DELETE, $user);

        if ($this->isCsrfTokenValid('admin_collaborator_delete_' . $user->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Le compte collaborateur #%d a été supprimé avec succès.', $user->getId()));
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Action de suppression annulée.');
        }

        return $this->redirectToRoute('admin_collaborator_index', [], Response::HTTP_SEE_OTHER);
    }

    private function handlePassword(User $user, FormInterface $form): void
    {
        if ($form->get('plainPassword')->getData()) {
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $form->get('plainPassword')->getData())
            );
        }
    }
}
