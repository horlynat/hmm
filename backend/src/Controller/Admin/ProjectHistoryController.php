<?php

namespace App\Controller\Admin;

use App\Entity\Project;
use App\Repository\ProjectHistoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route; // ✅ Correction : Importation du bon namespace d'Attribut

/**
 * Contrôleur pour la gestion et le suivi de l'historique des projets.
 *
 * 🔒 Sécurité :
 * - Accès strictement réservé aux comptes dotés du rôle ROLE_ADMIN.
 * - Validation du format de l'identifiant ({id} doit être un entier numérique).
 */
#[Route('/admin/project/{id}/history', name: 'admin_project_history_', requirements: ['id' => '\d+'])]
class ProjectHistoryController extends AbstractController
{
    // =========================================================================
    // 📌 HISTORIQUE COMPLET D'UN PROJET
    // =========================================================================

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Project $project, ProjectHistoryRepository $historyRepo): Response
    {
        // 🛡️ Protection de l'accès au niveau applicatif
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $histories = $historyRepo->findByProjectOrderedByDate($project->getId());

        return $this->render('admin/project/history.html.twig', [ // ✅ Harmonisé avec le dossier admin/
            'project' => $project,
            'histories' => $histories,
        ]);
    }

    // =========================================================================
    // 📌 HISTORIQUE RÉCENT (FRAGMENT AJAX / SUB-VIEW)
    // =========================================================================

    #[Route('/recent', name: 'recent', methods: ['GET'])]
    public function recent(Project $project, ProjectHistoryRepository $historyRepo): Response
    {
        // 🛡️ Protection de l'accès au niveau applicatif
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $histories = $historyRepo->findRecentByProject($project->getId(), 5);

        return $this->render('admin/project/_history_list.html.twig', [ // ✅ Harmonisé avec le dossier admin/
            'project' => $project,
            'histories' => $histories,
        ]);
    }
}
