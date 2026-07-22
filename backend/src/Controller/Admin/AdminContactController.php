<?php

namespace App\Controller\Admin;

use App\Entity\ContactMessage;
use App\Enum\ContactMessageStatusEnum;
use App\Repository\ContactMessageRepository;
use App\Security\Voter\ContactVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur pour la gestion des messages du formulaire de contact.
 *
 * 🔒 Sécurité :
 * - Réservé exclusivement aux utilisateurs dotés du rôle ROLE_ADMIN.
 * - Validation stricte des jetons CSRF pour les actions destructrices.
 *
 * ✅ Fonctionnalités :
 * - Boîte de réception avec statuts Nouveau / Lu / Archivé.
 * - Passage automatique en "Lu" à l'ouverture d'un message.
 */
#[Route('/admin/contact', name: 'admin_contact_')]
final class AdminContactController extends AbstractController
{
    // =========================================================================
    // 📌 LISTE DES MESSAGES
    // =========================================================================

    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(Request $request, ContactMessageRepository $contactMessageRepository): Response
    {
        $this->denyAccessUnlessGranted(ContactVoter::VIEW);

        $statusFilter = $request->query->get('status', '');
        $search = trim((string) $request->query->get('search', ''));

        $queryBuilder = $contactMessageRepository->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC');

        if ('' !== $statusFilter) {
            try {
                $queryBuilder->andWhere('c.status = :status')
                    ->setParameter('status', ContactMessageStatusEnum::from($statusFilter));
            } catch (\ValueError) {
                // Statut invalide : ignoré silencieusement, aucun filtre appliqué.
            }
        }

        if ('' !== $search) {
            $queryBuilder->andWhere('c.subject LIKE :search OR c.name LIKE :search OR c.email LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        $messages = $queryBuilder->getQuery()->getResult();

        return $this->render('admin/contact/messages.html.twig', [
            'messages' => $messages,
            'statuses' => ContactMessageStatusEnum::cases(),
            'filters' => [
                'status' => $statusFilter,
                'search' => $search,
            ],
            'unreadCount' => $contactMessageRepository->countUnread(),
        ]);
    }

    // =========================================================================
    // 📌 DÉTAIL D'UN MESSAGE (MARQUAGE AUTOMATIQUE "LU")
    // =========================================================================

    #[Route('/{id}/read', name: 'read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(ContactMessage $contactMessage, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(ContactVoter::VIEW, $contactMessage);

        if (ContactMessageStatusEnum::NEW === $contactMessage->getStatus()) {
            $contactMessage->markAsRead();
            $entityManager->flush();
        }

        return $this->render('admin/contact/read.html.twig', [
            'message' => $contactMessage,
        ]);
    }

    // =========================================================================
    // 📌 ARCHIVAGE D'UN MESSAGE
    // =========================================================================

    #[Route('/{id}/archive', name: 'archive', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function archive(ContactMessage $contactMessage, EntityManagerInterface $entityManager, Request $request, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(ContactVoter::ARCHIVE, $contactMessage);

        if ($this->isCsrfTokenValid('archive_'.$contactMessage->getId(), $request->request->get('_token'))) {
            $contactMessage->archive();
            $auditLogger->log(ContactMessage::class, $contactMessage->getId(), $contactMessage->getSubject(), 'archived');
            $entityManager->flush();

            $this->addFlash('success', 'Le message a été archivé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_contact_read', ['id' => $contactMessage->getId()]);
    }

    // =========================================================================
    // 📌 SUPPRESSION D'UN MESSAGE
    // =========================================================================

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(ContactMessage $contactMessage, EntityManagerInterface $entityManager, Request $request, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(ContactVoter::DELETE, $contactMessage);

        if ($this->isCsrfTokenValid('admin_contact_delete_'.$contactMessage->getId(), $request->request->get('_token'))) {
            $auditLogger->log(ContactMessage::class, $contactMessage->getId(), $contactMessage->getSubject(), 'deleted');
            $entityManager->remove($contactMessage);
            $entityManager->flush();

            $this->addFlash('success', 'Le message a été supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Action de suppression annulée.');
        }

        return $this->redirectToRoute('admin_contact_index', [], Response::HTTP_SEE_OTHER);
    }
}
