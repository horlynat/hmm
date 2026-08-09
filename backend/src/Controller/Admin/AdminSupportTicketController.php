<?php

namespace App\Controller\Admin;

use App\Entity\SupportTicket;
use App\Entity\SupportTicketMessage;
use App\Enum\SupportTicketStatusEnum;
use App\Repository\SupportTicketRepository;
use App\Security\Voter\SupportTicketVoter;
use App\Service\AccountLinkResolver;
use App\Service\AuditLogger;
use App\Service\EmailManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur pour la gestion des tickets de support client.
 *
 * 🔒 Sécurité :
 * - VIEW/REPLY/RESOLVE réservés à ROLE_MODERATOR et plus, DELETE à ROLE_MANAGER.
 * - Validation stricte des jetons CSRF pour les actions de statut et de suppression.
 *
 * ✅ Cycle de vie (SupportTicketStatusEnum) :
 *
 *   ouvert ──réponse admin──▶ en cours ──résoudre──▶ résolu
 *      ▲                                                │
 *      └──────────────── réponse client ─────────────────┘
 *      (réponse client sur un ticket résolu = réouverture automatique, cf.
 *      SupportTicket::reopenIfResolved())
 */
#[Route('/admin/support/ticket', name: 'admin_support_ticket_')]
final class AdminSupportTicketController extends AbstractController
{
    // =========================================================================
    // 📌 LISTE DES TICKETS
    // =========================================================================

    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(Request $request, SupportTicketRepository $supportTicketRepository): Response
    {
        $this->denyAccessUnlessGranted(SupportTicketVoter::VIEW);

        $statusFilter = $request->query->get('status', '');
        $search = trim((string) $request->query->get('search', ''));

        $queryBuilder = $supportTicketRepository->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC');

        $status = SupportTicketStatusEnum::tryFrom($statusFilter);
        if (null !== $status) {
            $queryBuilder->andWhere('t.status = :status')
                ->setParameter('status', $status);
        }

        if ('' !== $search) {
            $queryBuilder->andWhere('t.subject LIKE :search OR t.name LIKE :search OR t.email LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        return $this->render('admin/support_ticket/tickets.html.twig', [
            'tickets' => $queryBuilder->getQuery()->getResult(),
            'statuses' => SupportTicketStatusEnum::cases(),
            'filters' => [
                'status' => $statusFilter,
                'search' => $search,
            ],
            'openCount' => $supportTicketRepository->countOpenOrInProgress(),
        ]);
    }

    // =========================================================================
    // 📌 DÉTAIL D'UN TICKET (FIL DE MESSAGES)
    // =========================================================================

    #[Route('/{id}/read', name: 'read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(SupportTicket $supportTicket): Response
    {
        $this->denyAccessUnlessGranted(SupportTicketVoter::VIEW, $supportTicket);

        return $this->render('admin/support_ticket/read.html.twig', [
            'ticket' => $supportTicket,
        ]);
    }

    // =========================================================================
    // 📌 RÉPONDRE À UN TICKET (passe en "en cours", quel que soit l'état précédent)
    // =========================================================================

    #[Route('/{id}/reply', name: 'reply', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reply(
        SupportTicket $supportTicket,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
        EmailManager $emailManager,
        AccountLinkResolver $accountLinkResolver,
    ): Response {
        $this->denyAccessUnlessGranted(SupportTicketVoter::REPLY, $supportTicket);

        if (!$this->isCsrfTokenValid('support_reply_'.$supportTicket->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');

            return $this->redirectToRoute('admin_support_ticket_read', ['id' => $supportTicket->getId()]);
        }

        $body = trim((string) $request->request->get('body', ''));
        if (mb_strlen($body) < 10) {
            $this->addFlash('error', 'La réponse doit contenir au moins 10 caractères.');

            return $this->redirectToRoute('admin_support_ticket_read', ['id' => $supportTicket->getId()]);
        }

        $message = new SupportTicketMessage();
        $message->setBody($body);
        $message->setFromAdmin(true);
        $supportTicket->addMessage($message);
        $supportTicket->setStatus(SupportTicketStatusEnum::IN_PROGRESS);
        $auditLogger->log(SupportTicket::class, $supportTicket->getId(), $supportTicket->getSubject(), 'replied');
        $entityManager->flush();

        $this->notifyReply($supportTicket, $body, $emailManager, $accountLinkResolver);

        $this->addFlash('success', 'La réponse a été envoyée. Le client a été notifié par email.');

        return $this->redirectToRoute('admin_support_ticket_read', ['id' => $supportTicket->getId()]);
    }

    /**
     * Notifie le client par email de la réponse admin. Envoyée à l'adresse
     * renseignée sur le ticket, qui reste valide que le ticket soit lié à un
     * compte ou non (visiteur anonyme).
     */
    private function notifyReply(SupportTicket $supportTicket, string $replyBody, EmailManager $emailManager, AccountLinkResolver $accountLinkResolver): void
    {
        $emailManager->sendAsync(
            to: $supportTicket->getEmail(),
            subject: 'Nouvelle réponse à votre ticket',
            template: 'support_ticket_reply',
            context: [
                'clientName' => $supportTicket->getName(),
                'subject' => $supportTicket->getSubject(),
                'replyBody' => $replyBody,
                'threadUrl' => $accountLinkResolver->resolveGuestLink('/support/'.$supportTicket->getAccessToken()),
            ],
        );
    }

    // =========================================================================
    // 📌 MARQUER RÉSOLU (uniquement depuis "ouvert"/"en cours" — pas d'email,
    //    une résolution n'est pas un message)
    // =========================================================================

    #[Route('/{id}/resolve', name: 'resolve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function resolve(SupportTicket $supportTicket, EntityManagerInterface $entityManager, Request $request, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(SupportTicketVoter::RESOLVE, $supportTicket);

        if (!$this->isCsrfTokenValid('support_status_'.$supportTicket->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');

            return $this->redirectToRoute('admin_support_ticket_read', ['id' => $supportTicket->getId()]);
        }

        if (SupportTicketStatusEnum::RESOLVED === $supportTicket->getStatus()) {
            $this->addFlash('error', 'Ce ticket est déjà résolu.');

            return $this->redirectToRoute('admin_support_ticket_read', ['id' => $supportTicket->getId()]);
        }

        $supportTicket->setStatus(SupportTicketStatusEnum::RESOLVED);
        $auditLogger->log(SupportTicket::class, $supportTicket->getId(), $supportTicket->getSubject(), 'resolved');
        $entityManager->flush();

        $this->addFlash('success', 'Le ticket a été marqué comme résolu.');

        return $this->redirectToRoute('admin_support_ticket_read', ['id' => $supportTicket->getId()]);
    }

    // =========================================================================
    // 📌 SUPPRESSION D'UN TICKET (cascade sur les messages du fil)
    // =========================================================================

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(SupportTicket $supportTicket, EntityManagerInterface $entityManager, Request $request, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(SupportTicketVoter::DELETE, $supportTicket);

        if ($this->isCsrfTokenValid('admin_support_ticket_delete_'.$supportTicket->getId(), $request->request->get('_token'))) {
            $auditLogger->log(SupportTicket::class, $supportTicket->getId(), $supportTicket->getSubject(), 'deleted');
            $entityManager->remove($supportTicket);
            $entityManager->flush();

            $this->addFlash('success', 'Le ticket a été supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Action de suppression annulée.');
        }

        return $this->redirectToRoute('admin_support_ticket_index', [], Response::HTTP_SEE_OTHER);
    }
}
