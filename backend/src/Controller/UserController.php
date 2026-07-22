<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Security\Voter\UserVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Form\FormInterface;

#[Route('/admin/users', name: 'user_')]
final class UserController extends AbstractController
{
    /**
     * Constructeur
     * Injection des dépendances principales pour la gestion des utilisateurs.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {}

    /**
     * 📌 Liste des utilisateurs
     * - Récupère tous les utilisateurs
     * - Affiche la vue Twig correspondante
     */
    #[Route(name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $this->userRepository->findClients();

        return $this->render('user/index.html.twig', [
            'users' => $users,
        ]);
    }

    /**
     * 📌 Créer un nouvel utilisateur
     * - Affiche un formulaire UserType
     * - Hash le mot de passe si fourni
     * - Persiste l’utilisateur en base
     */
    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePassword($user, $form);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf("Utilisateur #%d créé avec succès !", $user->getId()));
            return $this->redirectToRoute('user_index');
        }

        return $this->render('user/create.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * 📌 Afficher un utilisateur
     * - Affiche les détails d’un utilisateur
     * - Retourne une erreur si l’utilisateur n’existe pas
     */
    #[Route('/{id}', name: 'read', methods: ['GET'])]
    public function read(int $id): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw $this->createNotFoundException("Utilisateur #$id introuvable.");
        }

        $this->denyAccessUnlessGranted(UserVoter::VIEW, $user);

        return $this->render('user/read.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * 📌 Modifier un utilisateur
     * - Affiche un formulaire UserType
     * - Met à jour les informations
     * - Hash le mot de passe si modifié
     * - Met à jour la date de modification
     */
    #[Route('/{id}/edit', name: 'update', methods: ['GET', 'POST'])]
    public function update(Request $request, int $id): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw $this->createNotFoundException("Utilisateur #$id introuvable.");
        }

        $this->denyAccessUnlessGranted(UserVoter::EDIT, $user);

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePassword($user, $form);

            $user->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->addFlash('success', sprintf("Utilisateur #%d mis à jour avec succès !", $user->getId()));
            return $this->redirectToRoute('user_index');
        }

        return $this->render('user/update.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * 📌 Supprimer un utilisateur
     * - Vérifie le token CSRF
     * - Supprime l’utilisateur si valide
     */
    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw $this->createNotFoundException("Utilisateur #$id introuvable.");
        }

        $this->denyAccessUnlessGranted(UserVoter::DELETE, $user);

        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf("Utilisateur #%d supprimé avec succès !", $user->getId()));
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');
        }

        return $this->redirectToRoute('user_index');
    }

    
    /**
     * 🔒 Méthode privée pour gérer le hash du mot de passe
     * - Vérifie si plainPassword est défini
     * - Hash et définit le mot de passe
     */
    private function handlePassword(User $user, FormInterface $form): void
    {
        if ($form->get('plainPassword')->getData()) {
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $form->get('plainPassword')->getData())
            );
        }
    }

}
