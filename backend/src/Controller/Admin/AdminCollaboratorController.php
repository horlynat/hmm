<?php

namespace App\Controller\Admin;

use App\Entity\CandidateMessage;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\CandidateMessageRepository;
use App\Repository\UserRepository;
use App\Security\Voter\UserVoter;
use App\Service\AccountLinkResolver;
use App\Service\AccountWelcomeNotifier;
use App\Service\AuditLogger;
use App\Service\EmailManager;
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
        private readonly AccountWelcomeNotifier $accountWelcomeNotifier,
        private readonly CandidateMessageRepository $candidateMessageRepository,
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
            'unreadCounts' => $this->candidateMessageRepository->countUnreadFromCandidatesGroupedByCandidate(),
            'candidates' => $this->userRepository->findFreelanceCandidates(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = new User();
        $user->setRoles(['ROLE_EDITOR']);
        $user->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(UserType::class, $user, ['context' => 'collaborator']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePassword($user, $form);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $auditLogger->log(User::class, $user->getId(), $user->getEmail(), 'created');
            $this->entityManager->flush();
            $this->accountWelcomeNotifier->accountCreated($user, 'collaborateur');

            $this->addFlash('success', sprintf('Le compte collaborateur #%d a été créé avec succès. Il a été notifié par email.', $user->getId()));
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

        // Consulter le fil vaut accusé de lecture des réponses du candidat,
        // même logique que AdminSupportTicketController::read() (implicite là
        // via le statut) — ici explicite puisque CandidateMessage porte son
        // propre indicateur $read par sens.
        $this->candidateMessageRepository->markReadFor($user, fromAdmin: false);
        $this->entityManager->flush();

        return $this->render('admin/collaborator/read.html.twig', [
            'user' => $user,
            'messages' => $this->candidateMessageRepository->findForCandidate($user),
        ]);
    }

    // =========================================================================
    // 📌 CONVERSATION AVEC LE CANDIDAT (fil de messages admin <-> candidat)
    // =========================================================================

    #[Route('/{id}/messages', name: 'message_send', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function sendMessage(
        User $user,
        Request $request,
        AuditLogger $auditLogger,
        EmailManager $emailManager,
        AccountLinkResolver $accountLinkResolver,
    ): Response {
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $user);

        // conv=1 sur chaque redirect ci-dessous : rouvre automatiquement la
        // modale de conversation (cf. read.html.twig) après l'action, plutôt
        // que de renvoyer sur la fiche candidat fermée — l'admin reste dans
        // le fil qu'il était en train de lire/écrire.
        if (!$this->isCsrfTokenValid('candidate_message_'.$user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');

            return $this->redirectToRoute('admin_collaborator_read', ['id' => $user->getId(), 'conv' => 1]);
        }

        $body = trim((string) $request->request->get('body', ''));
        if (mb_strlen($body) < 10) {
            $this->addFlash('error', 'Le message doit contenir au moins 10 caractères.');

            return $this->redirectToRoute('admin_collaborator_read', ['id' => $user->getId(), 'conv' => 1]);
        }

        $message = new CandidateMessage();
        $message->setCandidate($user);
        $message->setBody($body);
        $message->setFromAdmin(true);
        $this->entityManager->persist($message);
        $auditLogger->log(User::class, $user->getId(), $user->getEmail(), 'message_sent');
        $this->entityManager->flush();

        $emailManager->sendAsync(
            to: $user->getEmail(),
            subject: 'Nouveau message concernant votre candidature',
            template: 'candidate_message_received',
            context: [
                'fullName' => $user->getFullName(),
                'messageBody' => $body,
                'messagesUrl' => $accountLinkResolver->resolve(
                    $user,
                    'admin_collaborator_read',
                    ['id' => $user->getId()],
                    '/compte/messages',
                ),
            ],
        );

        $this->addFlash('success', 'Le message a été envoyé. Le candidat a été notifié par email.');

        return $this->redirectToRoute('admin_collaborator_read', ['id' => $user->getId(), 'conv' => 1]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function update(Request $request, User $user, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $user);

        $form = $this->createForm(UserType::class, $user, ['context' => 'collaborator']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePassword($user, $form);
            $user->setUpdatedAt(new \DateTimeImmutable());
            $auditLogger->log(User::class, $user->getId(), $user->getEmail(), 'updated');
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
    public function delete(Request $request, User $user, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::DELETE, $user);

        if ($this->isCsrfTokenValid('admin_collaborator_delete_' . $user->getId(), $request->request->get('_token'))) {
            $auditLogger->log(User::class, $user->getId(), $user->getEmail(), 'deleted');
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
