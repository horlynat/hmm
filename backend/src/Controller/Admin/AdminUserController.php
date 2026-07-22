<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\NotificationPriorityEnum;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Security\Voter\UserVoter;
use App\Service\AdminAlertNotifier;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur pour la gestion des comptes administrateurs (rôles, permissions).
 *
 * 🔒 Sécurité :
 * - Réservé exclusivement aux administrateurs (ROLE_ADMIN).
 * - Les règles fines (auto-suppression, protection des comptes Super Administrateur)
 *   sont centralisées dans UserVoter afin de s'appliquer de la même façon quel que
 *   soit le contrôleur (Admins, Collaborateurs, Clients) utilisé pour atteindre le compte.
 */
#[Route('/admin/admins', name: 'admin_user_')]
final class AdminUserController extends AbstractController
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

        return $this->render('admin/user/index.html.twig', [
            'admins' => $this->userRepository->findAdmins(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);
        $user->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePassword($user, $form);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $auditLogger->log(User::class, $user->getId(), $user->getEmail(), 'created');
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Le compte administrateur #%d a été créé avec succès.', $user->getId()));
            return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/user/create.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(User $user): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $user);

        return $this->render('admin/user/read.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function update(Request $request, User $user, AuditLogger $auditLogger, AdminAlertNotifier $adminAlertNotifier): Response
    {
        if (!$this->isGranted(UserVoter::EDIT, $user)) {
            $this->addFlash('error', 'Seul un Super Administrateur peut modifier ce compte.');
            return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        $wasAdmin = \in_array('ROLE_ADMIN', $user->getRoles(), true) || \in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $stillAdmin = \in_array('ROLE_ADMIN', $user->getRoles(), true) || \in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);

            if ($user === $this->getUser() && $wasAdmin && !$stillAdmin) {
                $this->addFlash('error', 'Vous ne pouvez pas retirer votre propre rôle administrateur.');
                return $this->redirectToRoute('admin_user_update', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
            }

            $this->handlePassword($user, $form);
            $user->setUpdatedAt(new \DateTimeImmutable());
            $auditLogger->log(User::class, $user->getId(), $user->getEmail(), 'updated');
            $this->entityManager->flush();

            if (!$wasAdmin && $stillAdmin) {
                $actor = $this->getUser();
                $adminAlertNotifier->alert(
                    NotificationPriorityEnum::HIGH,
                    'Élévation de privilèges',
                    sprintf(
                        '%s a été promu au rôle administrateur par %s.',
                        $user->getEmail(),
                        $actor instanceof User ? $actor->getEmail() : 'un administrateur',
                    ),
                );
            }

            $this->addFlash('success', sprintf('Le compte administrateur #%d a été mis à jour avec succès.', $user->getId()));
            return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/user/update.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, User $user, AuditLogger $auditLogger): Response
    {
        if (!$this->isGranted(UserVoter::DELETE, $user)) {
            $message = $user === $this->getUser()
                ? 'Vous ne pouvez pas supprimer votre propre compte.'
                : 'Seul un Super Administrateur peut supprimer ce compte.';
            $this->addFlash('error', $message);
            return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('admin_user_delete_' . $user->getId(), $request->request->get('_token'))) {
            $auditLogger->log(User::class, $user->getId(), $user->getEmail(), 'deleted');
            $this->entityManager->remove($user);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Le compte administrateur #%d a été supprimé avec succès.', $user->getId()));
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Action de suppression annulée.');
        }

        return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
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
