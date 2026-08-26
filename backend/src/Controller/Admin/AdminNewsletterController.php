<?php

namespace App\Controller\Admin;

use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use App\Security\Voter\NewsletterVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur pour la consultation des abonnés à la newsletter (cf.
 * App\Entity\NewsletterSubscriber, formulaire public NewsletterForm côté
 * frontend, double opt-in géré par NewsletterSubscriberCreateProcessor +
 * NewsletterConfirmationController).
 *
 * 🔒 Sécurité :
 * - VIEW réservé à Modérateur et plus (mêmes adresses e-mail de visiteurs
 *   que ContactVoter), DELETE à Manager et plus.
 * - Validation stricte des jetons CSRF pour la suppression.
 *
 * Pas de création/édition ici : un abonné n'existe que via l'inscription
 * publique (NewsletterSubscriberCreateProcessor) — l'admin consulte, filtre
 * et peut retirer un abonné, rien de plus.
 */
#[Route('/admin/newsletter', name: 'admin_newsletter_')]
final class AdminNewsletterController extends AbstractController
{
    // =========================================================================
    // 📌 LISTE DES ABONNÉS
    // =========================================================================

    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(Request $request, NewsletterSubscriberRepository $subscriberRepository): Response
    {
        $this->denyAccessUnlessGranted(NewsletterVoter::VIEW);

        $statusFilter = $request->query->get('status', '');
        $search = trim((string) $request->query->get('search', ''));

        $queryBuilder = $subscriberRepository->createQueryBuilder('s')
            ->orderBy('s.subscribedAt', 'DESC');

        if ('confirmed' === $statusFilter) {
            $queryBuilder->andWhere('s.confirmedAt IS NOT NULL')->andWhere('s.unsubscribedAt IS NULL');
        } elseif ('pending' === $statusFilter) {
            $queryBuilder->andWhere('s.confirmedAt IS NULL')->andWhere('s.unsubscribedAt IS NULL');
        } elseif ('unsubscribed' === $statusFilter) {
            $queryBuilder->andWhere('s.unsubscribedAt IS NOT NULL');
        }

        if ('' !== $search) {
            $queryBuilder->andWhere('s.email LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        return $this->render('admin/newsletter/index.html.twig', [
            'subscribers' => $queryBuilder->getQuery()->getResult(),
            'filters' => [
                'status' => $statusFilter,
                'search' => $search,
            ],
            'confirmedCount' => $subscriberRepository->countConfirmed(),
            'pendingCount' => $subscriberRepository->countPending(),
        ]);
    }

    // =========================================================================
    // 📌 SUPPRESSION D'UN ABONNÉ
    // =========================================================================

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(NewsletterSubscriber $subscriber, EntityManagerInterface $entityManager, Request $request, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(NewsletterVoter::DELETE, $subscriber);

        if ($this->isCsrfTokenValid('admin_newsletter_delete_'.$subscriber->getId(), $request->request->get('_token'))) {
            $auditLogger->log(NewsletterSubscriber::class, $subscriber->getId(), $subscriber->getEmail(), 'deleted');
            $entityManager->remove($subscriber);
            $entityManager->flush();

            $this->addFlash('success', "L'abonné a été supprimé avec succès.");
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Action de suppression annulée.');
        }

        return $this->redirectToRoute('admin_newsletter_index', [], Response::HTTP_SEE_OTHER);
    }
}
