<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectStatusEnum;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service pour calculer les statistiques liées aux projets.
 * - Statistiques globales par utilisateur
 * - Statistiques détaillées par projet
 * - Données pour les graphiques du dashboard
 */
class ProjectStatisticsService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * 📊 Statistiques globales pour un utilisateur
     */
    public function getUserProjectStatistics(User $user): array
    {
        $query = $this->entityManager->createQuery(
            'SELECT
                SUM(p.budget) AS totalBudget,
                SUM(p.spent) AS totalSpent,
                COUNT(p.id) AS totalProjects,
                SUM(CASE WHEN p.spent > p.budget THEN 1 ELSE 0 END) AS overBudgetCount,
                SUM(CASE WHEN p.budget > 0 AND (p.budget - p.spent) / p.budget < 0.1 THEN 1 ELSE 0 END) AS lowBudgetCount
             FROM App\Entity\Project p
             LEFT JOIN p.collaborators c
             WHERE p.owner = :user OR c.id = :user'
        )->setParameter('user', $user);

        // ✅ getSingleResult car plusieurs colonnes
        $result = $query->getSingleResult();

        $totalBudget = (float) ($result['totalBudget'] ?? 0);
        $totalSpent = (float) ($result['totalSpent'] ?? 0);

        return [
            'totalBudget' => $totalBudget,
            'totalSpent' => $totalSpent,
            'totalProjects' => (int) ($result['totalProjects'] ?? 0),
            'overBudgetCount' => (int) ($result['overBudgetCount'] ?? 0),
            'lowBudgetCount' => (int) ($result['lowBudgetCount'] ?? 0),
            'remainingBudget' => $totalBudget - $totalSpent,
        ];
    }

    /**
     * 📊 Statistiques détaillées pour un projet
     */
    public function getProjectStatistics(Project $project): array
    {
        $expenses = $project->getExpenses();
        $totalSpent = 0.0;

        foreach ($expenses as $expense) {
            $totalSpent += (float) $expense->getAmount();
        }

        $budget = (float) $project->getBudget();
        $remainingBudget = $budget - $totalSpent;

        // ✅ Calcul correct du pourcentage utilisé
        $percentageUsed = $budget > 0
            ? min(100, round(($totalSpent / $budget) * 100, 2))
            : 0;

        return [
            'totalBudget' => $budget,
            'totalSpent' => $totalSpent,
            'remainingBudget' => $remainingBudget,
            'percentageUsed' => $percentageUsed,
            'expenseCount' => count($expenses),
            'historyCount' => count($project->getHistories()),
            'collaboratorCount' => count($project->getCollaborators()),
        ];
    }

    /**
     * 📊 Données pour les graphiques du dashboard
     */
    public function getChartData(): array
    {
        $entityManager = $this->entityManager;

        // Projets par statut
        $projectsByStatus = [];
        foreach (ProjectStatusEnum::cases() as $status) {
            $count = $entityManager->getRepository(Project::class)->count(['status' => $status]);
            $projectsByStatus[$status->getLabel()] = $count;
        }

        // Budget par statut
        $budgetByStatus = [];
        foreach (ProjectStatusEnum::cases() as $status) {
            $result = $entityManager->createQuery(
                'SELECT SUM(p.budget) AS total FROM App\Entity\Project p WHERE p.status = :status'
            )->setParameter('status', $status)
             ->getSingleScalarResult();

            $budgetByStatus[$status->getLabel()] = (float) ($result ?? 0);
        }

        // Dépenses par mois (6 derniers mois)
        $expensesByMonth = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = (new \DateTime())->modify("-$i months")->format('Y-m');
            $result = $entityManager->createQuery(
                'SELECT SUM(e.amount) AS total FROM App\Entity\ProjectExpense e
                 WHERE e.createdAt BETWEEN :start AND :end'
            )
            ->setParameter('start', new \DateTimeImmutable($month . '-01'))
            ->setParameter('end', (new \DateTimeImmutable($month . '-01'))->modify('+1 month -1 day'))
            ->getSingleScalarResult();

            $expensesByMonth[$month] = (float) ($result ?? 0);
        }

        return [
            'projectsByStatus' => $projectsByStatus,
            'budgetByStatus' => $budgetByStatus,
            'expensesByMonth' => $expensesByMonth,
        ];
    }
}
