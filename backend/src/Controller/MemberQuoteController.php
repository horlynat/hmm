<?php

namespace App\Controller;

use App\Entity\QuoteRequest;
use App\Entity\User;
use App\Repository\QuoteRequestRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Espace membre (client) — consultation de SES propres demandes de devis.
 * Distinct du back-office admin (/admin/request, réservé QuoteVoter::VIEW soit
 * ROLE_MANAGER+) : accès dès ROLE_USER, périmètre borné au demandeur lui-même
 * (pas de Voter dédié ici — QuoteVoter est un pur seuil de rôle, sans notion
 * de propriétaire, donc l'appartenance est vérifiée directement ci-dessous).
 */
#[Route('/mes-devis', name: 'member_quote_')]
final class MemberQuoteController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(QuoteRequestRepository $quoteRequestRepository): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $this->render('member/quote/index.html.twig', [
            'quotes' => $quoteRequestRepository->findByUser($user),
        ]);
    }

    #[Route('/{id}', name: 'read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(QuoteRequest $quoteRequest): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        if ($quoteRequest->getUser()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        return $this->render('member/quote/read.html.twig', [
            'quoteRequest' => $quoteRequest,
        ]);
    }
}
