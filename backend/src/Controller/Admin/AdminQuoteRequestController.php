<?php

namespace App\Controller\Admin;

use App\Entity\Invoice;
use App\Entity\Media;
use App\Entity\Project;
use App\Entity\QuoteRequest;
use App\Entity\User;
use App\Enum\QuoteStatusEnum;
use App\Form\ProjectType;
use App\Repository\QuoteRequestRepository;
use App\Security\Voter\QuoteVoter;
use App\Service\AccountLinkResolver;
use App\Service\AuditLogger;
use App\Service\EmailManager;
use App\Service\MediaUploader;
use App\Service\ProjectNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

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
    public function accept(
        QuoteRequest $quoteRequest,
        EntityManagerInterface $entityManager,
        Request $request,
        AuditLogger $auditLogger,
        EmailManager $emailManager,
        AccountLinkResolver $accountLinkResolver,
    ): Response {
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
        $this->notifyQuoteDecision($quoteRequest, true, $emailManager, $accountLinkResolver);

        $this->addFlash('success', 'La demande a été acceptée. Le client a été notifié par email.');

        return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
    }

    // =========================================================================
    // 📌 REFUSER UNE DEMANDE (uniquement depuis "en attente" — une demande déjà
    //    acceptée ne peut plus être refusée, cf. suspend()/resume())
    // =========================================================================

    #[Route('/{id}/reject', name: 'reject', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reject(
        QuoteRequest $quoteRequest,
        EntityManagerInterface $entityManager,
        Request $request,
        AuditLogger $auditLogger,
        EmailManager $emailManager,
        AccountLinkResolver $accountLinkResolver,
    ): Response {
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
        $this->notifyQuoteDecision($quoteRequest, false, $emailManager, $accountLinkResolver);

        $this->addFlash('success', 'La demande a été refusée. Le client a été notifié par email.');

        return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
    }

    /**
     * Notifie le client par email de la décision (acceptée/refusée) sur sa
     * demande de devis. Envoyée à l'adresse renseignée sur la demande, qui
     * reste valide que la demande soit liée à un compte ou non (prospect
     * anonyme). Si un compte est lié, un lien vers l'espace concerné (front
     * ou back-office, selon le rôle) est ajouté.
     */
    private function notifyQuoteDecision(QuoteRequest $quoteRequest, bool $accepted, EmailManager $emailManager, AccountLinkResolver $accountLinkResolver): void
    {
        $user = $quoteRequest->getUser();
        $actionUrl = null !== $user
            ? $accountLinkResolver->resolve($user, 'admin_request_read', ['id' => $quoteRequest->getId()], '/compte/devis/'.$quoteRequest->getId())
            : null;

        $emailManager->sendAsync(
            to: $quoteRequest->getEmail(),
            subject: $accepted ? 'Votre demande de devis a été acceptée' : 'Votre demande de devis',
            template: 'quote_decision',
            context: [
                'clientName' => $quoteRequest->getName(),
                'accepted' => $accepted,
                'category' => $quoteRequest->getCategoryDetail() ?: $quoteRequest->getCategory(),
                'actionUrl' => $actionUrl,
            ],
        );
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
    // 📌 CONVERSION EN PROJET (uniquement depuis "acceptée", une seule fois) —
    //    ferme la boucle devis → projet suivi : crée un Project pré-rempli
    //    depuis la demande, génère la facture initiale si un budget est défini,
    //    et notifie le client par email.
    // =========================================================================

    #[Route('/{id}/convert', name: 'convert', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function convert(
        QuoteRequest $quoteRequest,
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        MediaUploader $mediaUploader,
        ProjectNotifier $projectNotifier,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(QuoteVoter::APPROVE, $quoteRequest);

        if (null !== $quoteRequest->getConvertedProject()) {
            return $this->redirectToRoute('admin_project_read', ['id' => $quoteRequest->getConvertedProject()->getId()]);
        }

        if (QuoteStatusEnum::ACCEPTED !== $quoteRequest->getStatus()) {
            $this->addFlash('error', 'Seule une demande acceptée peut être convertie en projet.');

            return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
        }

        $client = $quoteRequest->getUser();
        if (null === $client) {
            $this->addFlash('error', 'Cette demande n\'est liée à aucun compte client — impossible de créer un projet suivi côté client. Le client doit être connecté au moment de sa demande pour que le lien se fasse automatiquement.');

            return $this->redirectToRoute('admin_request_read', ['id' => $quoteRequest->getId()]);
        }

        $project = new Project();
        $project
            ->setTitle($quoteRequest->getCategoryDetail() ?: $quoteRequest->getCategory())
            ->setDescription($quoteRequest->getMessage())
            ->setClient($client)
            ->setOwner($this->getAuthenticatedUser())
            ->setSourceQuoteRequest($quoteRequest);

        $parsedBudget = $this->parseQuoteBudget($quoteRequest->getBudget());
        if (null !== $parsedBudget) {
            $project->setBudget($parsedBudget);
        }

        $form = $this->createForm(ProjectType::class, $project, [
            'include_planning' => true,
            'include_showcase' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $slug = $slugger->slug($project->getTitle())->lower();
            $project->setSlug($slug);

            $mediaFiles = $form->get('media')->getData();
            if ($mediaFiles && count($mediaFiles) > 0) {
                $results = $mediaUploader->uploadMultiple($mediaFiles, 'projects');
                foreach ($results as $result) {
                    $media = new Media();
                    $media
                        ->setFilePath($result['path'])
                        ->setAltText($project->getTitle())
                        ->setMimeType($result['mimeType'])
                        ->setSize($result['size'])
                        ->setType($result['type'])
                        ->setUploadedAt($result['uploadedAt']);
                    $entityManager->persist($media);
                    $project->addMedia($media);
                }
            }

            $project->logCreation($this->getAuthenticatedUser());
            $project->addToHistory(
                'converted_from_quote',
                $this->getAuthenticatedUser(),
                sprintf('Projet créé à partir de la demande de devis #%d', $quoteRequest->getId()),
            );

            // Facture initiale automatique — uniquement si un budget exploitable a
            // été renseigné (Invoice::amount exige un montant strictement positif) ;
            // sinon on ne fabrique pas un montant arbitraire, l'admin en créera une
            // manuellement une fois le budget défini.
            $invoice = null;
            if (bccomp($project->getBudget(), '0.00', 2) > 0) {
                $invoice = new Invoice();
                $invoice
                    ->setNumber($this->generateInvoiceNumber($entityManager))
                    ->setLabel('Facture initiale — '.$project->getTitle())
                    ->setAmount($project->getBudget())
                    ->setCurrency($quoteRequest->getCurrency() ?: 'EUR')
                    ->setDueDate((new \DateTimeImmutable())->modify('+14 days'));
                $project->addInvoice($invoice);
                $entityManager->persist($invoice);
            }

            $entityManager->persist($project);
            $entityManager->flush();

            $auditLogger->log(Project::class, $project->getId(), $project->getTitle(), 'created_from_quote');
            $projectNotifier->projectAccepted($project, $invoice);

            $this->addFlash('success', $invoice
                ? sprintf('Projet créé et facture %s générée automatiquement. Le client a été notifié par email.', $invoice->getNumber())
                : 'Projet créé. Aucune facture générée automatiquement (budget non défini) — le client a été notifié, pensez à créer une facture manuellement.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        return $this->render('admin/request/convert.html.twig', [
            'quoteRequest' => $quoteRequest,
            'project' => $project,
            'form' => $form->createView(),
        ]);
    }

    /** Budget libre du devis (ex. "5000", "5 000 €", "5000-8000") → montant décimal exploitable, ou null si non interprétable. */
    private function parseQuoteBudget(?string $raw): ?string
    {
        if (null === $raw || '' === trim($raw)) {
            return null;
        }

        // Ne garde que le premier nombre trouvé (utile pour les fourchettes "5000-8000" → 5000).
        if (!preg_match('/\d[\d\s]*(?:[.,]\d+)?/', $raw, $matches)) {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], $matches[0]);
        if (!is_numeric($normalized) || (float) $normalized <= 0) {
            return null;
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    private function generateInvoiceNumber(EntityManagerInterface $entityManager): string
    {
        $year = date('Y');
        $count = (int) $entityManager->createQuery(
            'SELECT COUNT(i.id) FROM App\Entity\Invoice i WHERE i.issuedAt >= :start'
        )->setParameter('start', new \DateTimeImmutable($year.'-01-01'))->getSingleScalarResult();

        return sprintf('FA-%s-%03d', $year, $count + 1);
    }

    /**
     * Récupère l'utilisateur authentifié en tant qu'App\Entity\User — cette route
     * est protégée en amont (ROLE_MANAGER via QuoteVoter), donc un utilisateur
     * non-User ne peut pas l'atteindre.
     */
    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Un utilisateur App\Entity\User authentifié est requis ici.');
        }

        return $user;
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
