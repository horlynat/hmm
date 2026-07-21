<?php

namespace App\Controller\Admin;

use App\Entity\QuoteRequest;
use App\Repository\QuoteRequestRepository;
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
 * ✅ Fonctionnalités :
 * - Statut : en attente (null) / accepté (true) / refusé (false).
 * - Actions Accepter / Refuser / Réinitialiser.
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $statusFilter = $request->query->get('status', '');
        $search = trim((string) $request->query->get('search', ''));

        $queryBuilder = $quoteRequestRepository->createQueryBuilder('q')
            ->leftJoin('q.user', 'u')
            ->orderBy('q.id', 'DESC');

        if ('pending' === $statusFilter) {
            $queryBuilder->andWhere('q.status IS NULL');
        } elseif ('accepted' === $statusFilter) {
            $queryBuilder->andWhere('q.status = true');
        } elseif ('rejected' === $statusFilter) {
            $queryBuilder->andWhere('q.status = false');
        }

        if ('' !== $search) {
            $queryBuilder->andWhere('q.name LIKE :search OR q.email LIKE :search OR q.message LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        return $this->render('admin/request/requests.html.twig', [
            'requests' => $queryBuilder->getQuery()->getResult(),
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/request/read.html.twig', [
            'quoteRequest' => $quoteRequest,
        ]);
    }

    // =========================================================================
    // 📌 ACCEPTER UNE DEMANDE
    // =========================================================================

    #[Route('/{id}/accept', name: 'accept', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function accept(QuoteRequest $quoteRequest, EntityManagerInterface $entityManager, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('request_status_'.$quoteRequest->getId(), $request->request->get('_token'))) {
            $quoteRequest->setStatus(true);
            $entityManager->flush();

            $this->addFlash('success', 'La demande a été acceptée.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
    }

    // =========================================================================
    // 📌 REFUSER UNE DEMANDE
    // =========================================================================

    #[Route('/{id}/reject', name: 'reject', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reject(QuoteRequest $quoteRequest, EntityManagerInterface $entityManager, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('request_status_'.$quoteRequest->getId(), $request->request->get('_token'))) {
            $quoteRequest->setStatus(false);
            $entityManager->flush();

            $this->addFlash('success', 'La demande a été refusée.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
    }

    // =========================================================================
    // 📌 RÉINITIALISER LE STATUT (RETOUR "EN ATTENTE")
    // =========================================================================

    #[Route('/{id}/reset', name: 'reset', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reset(QuoteRequest $quoteRequest, EntityManagerInterface $entityManager, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('request_status_'.$quoteRequest->getId(), $request->request->get('_token'))) {
            $quoteRequest->setStatus(null);
            $entityManager->flush();

            $this->addFlash('success', 'La demande a été remise en attente.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
    }

    // =========================================================================
    // 📌 SUPPRESSION D'UNE DEMANDE
    // =========================================================================

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(QuoteRequest $quoteRequest, EntityManagerInterface $entityManager, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('admin_request_delete_'.$quoteRequest->getId(), $request->request->get('_token'))) {
            $entityManager->remove($quoteRequest);
            $entityManager->flush();

            $this->addFlash('success', 'La demande a été supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Action de suppression annulée.');
        }

        return $this->redirectToRoute('admin_request_index', [], Response::HTTP_SEE_OTHER);
    }
}
