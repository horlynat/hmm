<?php

namespace App\Controller\Admin;

use App\Repository\AuditLogRepository;
use App\Repository\ProjectHistoryRepository;
use App\Security\Voter\SecurityVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vue unifiée "qui a fait quoi, quand" : fusionne le journal générique (AuditLog,
 * toutes les entités du back-office sauf Project) avec l'historique dédié des projets
 * (ProjectHistory, déjà existant) pour n'avoir qu'un seul écran de suivi.
 *
 * 🔒 Sécurité : réservé à SecurityVoter::VIEW_AUDIT (ROLE_ADMIN et plus).
 */
#[Route('/admin/security/audit', name: 'admin_security_audit_')]
class AdminSecurityAuditController extends AbstractController
{
    private const LIMIT = 50;

    /** Pool récupéré par source avant filtrage/tri, pour que le filtre reste pertinent. */
    private const FETCH_POOL = 200;

    /** Libellé humain de chaque code d'action, toutes sources confondues. */
    private const ACTIONS = [
        'created' => 'Création',
        'updated' => 'Modification',
        'deleted' => 'Suppression',
        'published' => 'Publication',
        'unpublished' => 'Dépublication',
        'accepted' => 'Acceptation',
        'rejected' => 'Refus',
        'reset' => 'Remise en attente',
        'archived' => 'Archivage',
        'status_changed' => 'Changement de statut',
        'expense_added' => 'Dépense ajoutée',
        'expense_removed' => 'Dépense retirée',
        'collaborator_added' => 'Collaborateur ajouté',
        'collaborator_removed' => 'Collaborateur retiré',
    ];

    /** Types d'entités trackées, dans l'ordre d'affichage du filtre. */
    private const TYPES = [
        'Project', 'Article', 'Skill', 'SkillCategory', 'Tag', 'Course', 'Experience',
        'Testimonial', 'ContactMessage', 'QuoteRequest', 'User',
    ];

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        AuditLogRepository $auditLogRepository,
        ProjectHistoryRepository $projectHistoryRepository,
    ): Response {
        $this->denyAccessUnlessGranted(SecurityVoter::VIEW_AUDIT);

        $actionFilter = (string) $request->query->get('action', '');
        $typeFilter = (string) $request->query->get('type', '');

        $entries = [];

        foreach ($auditLogRepository->findRecent(self::FETCH_POOL) as $auditLog) {
            $entries[] = [
                'date' => $auditLog->getCreatedAt(),
                'label' => sprintf('%s « %s »', $auditLog->getEntityShortName(), $auditLog->getEntityLabel()),
                'actionCode' => $auditLog->getAction(),
                'actionLabel' => $this->actionLabel($auditLog->getAction()),
                'user' => $auditLog->getUser(),
                'entityType' => $auditLog->getEntityShortName(),
                'source' => 'audit',
            ];
        }

        foreach ($projectHistoryRepository->findRecent(self::FETCH_POOL) as $history) {
            $entries[] = [
                'date' => $history->getCreatedAt(),
                'label' => sprintf('Projet « %s »', $history->getProject()->getTitle()),
                'actionCode' => $history->getAction(),
                'actionLabel' => $this->actionLabel($history->getAction()),
                'user' => $history->getUser(),
                'entityType' => 'Project',
                'source' => 'project',
            ];
        }

        if ('' !== $actionFilter) {
            $entries = array_filter($entries, static fn (array $e): bool => $e['actionCode'] === $actionFilter);
        }

        if ('' !== $typeFilter) {
            $entries = array_filter($entries, static fn (array $e): bool => $e['entityType'] === $typeFilter);
        }

        usort($entries, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        return $this->render('admin/security/audit.html.twig', [
            'entries' => array_slice($entries, 0, self::LIMIT),
            'actionOptions' => self::ACTIONS,
            'typeOptions' => self::TYPES,
            'actionFilter' => $actionFilter,
            'typeFilter' => $typeFilter,
        ]);
    }

    private function actionLabel(string $action): string
    {
        return self::ACTIONS[$action] ?? ucfirst($action);
    }
}
