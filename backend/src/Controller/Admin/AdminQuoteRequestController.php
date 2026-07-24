<?php

namespace App\Controller\Admin;

use App\Entity\QuoteRequest;
use App\Enum\QuoteStatusEnum;
use App\Repository\QuoteRequestRepository;
use App\Security\Voter\QuoteVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur pour la gestion des demandes de devis.
 *
 * 🔒 Sécurité :
 * - Réservé exclusivement aux utilisateurs dotés du rôle ROLE_ADMIN.
 * - Validation stricte des jetons CSRF pour les actions de statut et de suppression.
 *
 * ✅ Cycle de vie (QuoteStatusEnum) — reflète la réalité commerciale : une fois
 * une demande acceptée, on ne revient plus en arrière vers "refusée" (le client
 * a déjà dit oui) ; au pire les travaux sont suspendus temporairement puis
 * repris. "Refusée" est un état terminal (suppression possible mais pas
 * obligatoire).
 *
 *   en attente ──accept──▶ acceptée ──suspend──▶ suspendue
 *       │                     ▲                      │
 *       └──reject──▶ refusée  └───────resume──────────┘
 */
#[Route('/admin/request', name: 'admin_request_')]
final class AdminQuoteRequestController extends AbstractController
{
    // =========================================================================
    // 📌 LISTE DES DEMANDES
    // =========================================================================

    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(Request $request, QuoteRequestRepository $quoteRequestRepository): Response
    {
        $this->denyAccessUnlessGranted(QuoteVoter::VIEW);

        $statusFilter = $request->query->get('status', '');
        $search = trim((string) $request->query->get('search', ''));

        $queryBuilder = $quoteRequestRepository->createQueryBuilder('q')
            ->leftJoin('q.user', 'u')
            ->orderBy('q.id', 'DESC');

        $status = QuoteStatusEnum::tryFrom($statusFilter);
        if (null !== $status) {
            $queryBuilder->andWhere('q.status = :status')
                ->setParameter('status', $status);
        }

        if ('' !== $search) {
            $queryBuilder->andWhere('q.name LIKE :search OR q.email LIKE :search OR q.message LIKE :search OR q.category LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        return $this->render('admin/request/requests.html.twig', [
            'requests' => $queryBuilder->getQuery()->getResult(),
            'statuses' => QuoteStatusEnum::cases(),
            'filters' => [
                'status' => $statusFilter,
                'search' => $search,
            ],
        ]);
    }

    // =========================================================================
    // 📌 DÉTAIL D'UNE DEMANDE
    // =========================================================================

    #[Route('/{id}/read', name: 'read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(QuoteRequest $quoteRequest): Response
    {
        $this->denyAccessUnlessGranted(QuoteVoter::VIEW, $quoteRequest);

        return $this->render('admin/request/read.html.twig', [
            'quoteRequest' => $quoteRequest,
        ]);
    }

    // =========================================================================
    // 📌 ACCEPTER UNE DEMANDE (uniquement depuis "en attente")
    // =========================================================================

    #[Route('/{id}/accept', name: 'accept', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function accept(QuoteRequest $quoteRequest, EntityManagerInterface $entityManager, Request $request, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(QuoteVoter::APPROVE, $quoteRequest);

        if (!$this->isCsrfTokenValid('request_status_'.$quoteRequest->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');

            return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
        }

        if (QuoteStatusEnum::PENDING !== $quoteRequest->getStatus()) {
            $this->addFlash('error', 'Seule une demande en attente peut être acceptée.');

            return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
        }

        $quoteRequest->setStatus(QuoteStatusEnum::ACCEPTED);
        $auditLogger->log(QuoteRequest::class, $quoteRequest->getId(), $quoteRequest->getName(), 'accepted');
        $entityManager->flush();

        $this->addFlash('success', 'La demande a été acceptée.');

        return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
    }

    // =========================================================================
    // 📌 REFUSER UNE DEMANDE (uniquement depuis "en attente" — une demande déjà
    //    acceptée ne peut plus être refusée, cf. suspend()/resume())
    // =========================================================================

    #[Route('/{id}/reject', name: 'reject', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reject(QuoteRequest $quoteRequest, EntityManagerInterface $entityManager, Request $request, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(QuoteVoter::REJECT, $quoteRequest);

        if (!$this->isCsrfTokenValid('request_status_'.$quoteRequest->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');

            return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
        }

        if (QuoteStatusEnum::PENDING !== $quoteRequest->getStatus()) {
            $this->addFlash('error', 'Seule une demande en attente peut être refusée. Une demande déjà acceptée ne peut plus l\'être — suspendez-la si besoin.');

            return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
        }

        $quoteRequest->setStatus(QuoteStatusEnum::REJECTED);
        $auditLogger->log(QuoteRequest::class, $quoteRequest->getId(), $quoteRequest->getName(), 'rejected');
        $entityManager->flush();

        $this->addFlash('success', 'La demande a été refusée.');

        return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
    }

    // =========================================================================
    // 📌 SUSPENDRE TEMPORAIREMENT LES TRAVAUX (uniquement depuis "acceptée")
    // =========================================================================

    #[Route('/{id}/suspend', name: 'suspend', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function suspend(QuoteRequest $quoteRequest, EntityManagerInterface $entityManager, Request $request, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(QuoteVoter::EDIT, $quoteRequest);

        if (!$this->isCsrfTokenValid('request_status_'.$quoteRequest->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');

            return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
        }

        if (QuoteStatusEnum::ACCEPTED !== $quoteRequest->getStatus()) {
            $this->addFlash('error', 'Seule une demande acceptée peut être suspendue.');

            return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
        }

        $quoteRequest->setStatus(QuoteStatusEnum::SUSPENDED);
        $auditLogger->log(QuoteRequest::class, $quoteRequest->getId(), $quoteRequest->getName(), 'suspended');
        $entityManager->flush();

        $this->addFlash('success', 'Les travaux ont été suspendus temporairement.');

        return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
    }

    // =========================================================================
    // 📌 REPRENDRE LES TRAVAUX (uniquement depuis "suspendue")
    // =========================================================================

    #[Route('/{id}/resume', name: 'resume', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function resume(QuoteRequest $quoteRequest, EntityManagerInterface $entityManager, Request $request, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(QuoteVoter::EDIT, $quoteRequest);

        if (!$this->isCsrfTokenValid('request_status_'.$quoteRequest->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');

            return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
        }

        if (QuoteStatusEnum::SUSPENDED !== $quoteRequest->getStatus()) {
            $this->addFlash('error', 'Seule une demande suspendue peut être reprise.');

            return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
        }

        $quoteRequest->setStatus(QuoteStatusEnum::ACCEPTED);
        $auditLogger->log(QuoteRequest::class, $quoteRequest->getId(), $quoteRequest->getName(), 'resumed');
        $entityManager->flush();

        $this->addFlash('success', 'Les travaux ont repris.');

        return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
    }

    // =========================================================================
    // 📌 SUPPRESSION D'UNE DEMANDE (toujours possible, notamment pour purger
    //    les demandes refusées — mais pas obligatoire, elles peuvent rester
    //    comme historique)
    // =========================================================================

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(QuoteRequest $quoteRequest, EntityManagerInterface $entityManager, Request $request, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(QuoteVoter::DELETE, $quoteRequest);

        if ($this->isCsrfTokenValid('admin_request_delete_'.$quoteRequest->getId(), $request->request->get('_token'))) {
            $auditLogger->log(QuoteRequest::class, $quoteRequest->getId(), $quoteRequest->getName(), 'deleted');
            $entityManager->remove($quoteRequest);
            $entityManager->flush();

            $this->addFlash('success', 'La demande a été supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Action de suppression annulée.');
        }

        return $this->redirectToRoute('admin_request_index', [], Response::HTTP_SEE_OTHER);
    }
}
