<?php

namespace App\Repository;

use App\Entity\Project;
use App\Enum\ProjectStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour gérer les projets et leurs statistiques.
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class); // cite: 6
    }

    /**
     * 🔎 Recherche ultra-avancée globale de projets avec filtres dynamiques, tri et pagination.
     */
    public function findByFilters(array $filters = []): array
    {
        // Création du QueryBuilder (Suppression de la jointure sur p.tags qui causait l'erreur)
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.collaborators', 'c')
            ->addSelect('c'); // cite: 6, 7

        // 1. FILTRES STANDARDS & RECHERCHE GLOBALE
        if (!empty($filters['status'])) {
            $qb->andWhere('p.status = :status')
               ->setParameter('status', $filters['status']); // cite: 6, 7
        }

        if (!empty($filters['owner'])) {
            $qb->andWhere('p.owner = :owner')
               ->setParameter('owner', (int) $filters['owner']); // cite: 6, 7
        }

        if (!empty($filters['collaborator'])) {
            $qb->andWhere('c.id = :collaborator')
               ->setParameter('collaborator', (int) $filters['collaborator']); // cite: 6, 7
        }

        if (!empty($filters['title'])) {
            $qb->andWhere('p.title LIKE :title OR p.description LIKE :title')
               ->setParameter('title', '%' . $filters['title'] . '%'); // cite: 6, 7
        }

        // 2. FILTRES TEMPORELS (DATES & DÉLAIS)
        if (!empty($filters['date_start'])) {
            $qb->andWhere('p.createdAt >= :date_start')
               ->setParameter('date_start', new \DateTime($filters['date_start'])); // cite: 7
        }

        if (!empty($filters['date_end'])) {
            $qb->andWhere('p.createdAt <= :date_end')
               ->setParameter('date_end', new \DateTime($filters['date_end'] . ' 23:59:59')); // cite: 7
        }

        if (!empty($filters['time_urgency'])) {
            $now = new \DateTime();
            switch ($filters['time_urgency']) {
                case 'overdue': // En retard critique
                    $qb->andWhere('p.deadline < :now')
                       ->andWhere('p.status != :statusDone')
                       ->setParameter('now', $now)
                       ->setParameter('statusDone', ProjectStatusEnum::DONE); // cite: 7
                    break;
                case 'imminent': // Échéance sous 7 jours
                    $limit = (clone $now)->modify('+7 days');
                    $qb->andWhere('p.deadline BETWEEN :now AND :limit')
                       ->andWhere('p.status != :statusDone')
                       ->setParameter('now', $now)
                       ->setParameter('limit', $limit)
                       ->setParameter('statusDone', ProjectStatusEnum::DONE); // cite: 7
                    break;
            }
        }

        if (!empty($filters['inactive_days'])) {
            $inactiveLimit = (new \DateTime())->modify('-' . (int)$filters['inactive_days'] . ' days');
            $qb->andWhere('p.updatedAt <= :inactiveLimit')
               ->setParameter('inactiveLimit', $inactiveLimit); // cite: 7
        }

        // 3. FILTRES FINANCIERS AVANCÉS
        if (isset($filters['budget_min']) && $filters['budget_min'] !== '') {
            $qb->andWhere('p.budget >= :budget_min')
               ->setParameter('budget_min', (float) $filters['budget_min']); // cite: 7
        }

        if (isset($filters['budget_max']) && $filters['budget_max'] !== '') {
            $qb->andWhere('p.budget <= :budget_max')
               ->setParameter('budget_max', (float) $filters['budget_max']); // cite: 7
        }

        if (!empty($filters['billing_type'])) {
            $qb->andWhere('p.billingType = :billing_type')
               ->setParameter('billing_type', $filters['billing_type']); // cite: 7
        }

        if (!empty($filters['budget_status'])) {
            switch ($filters['budget_status']) {
                case 'over': // Dépassement Critique
                    $qb->andWhere('p.spent > p.budget'); // cite: 6, 7
                    break;
                case 'low': // Alerte Seuil (<10% restant)
                    $qb->andWhere('p.budget > 0')
                       ->andWhere('(p.budget - p.spent) / p.budget < 0.1'); // cite: 6, 7
                    break;
                case 'ok': // Budget Sain
                    $qb->andWhere('p.spent <= p.budget')
                       ->andWhere('(p.budget - p.spent) / p.budget >= 0.1'); // cite: 6, 7
                    break;
                case 'profitable': // Rentable & Terminé
                    $qb->andWhere('p.status = :statusDone')
                       ->andWhere('p.spent < p.budget')
                       ->setParameter('statusDone', ProjectStatusEnum::DONE); // cite: 7
                    break;
            }
        }

        // 4. NATURE, TYPOLOGIE & STRUCTURE RH
        if (!empty($filters['priority'])) {
            $qb->andWhere('p.priority = :priority')
               ->setParameter('priority', $filters['priority']); // cite: 7
        }

        // 📌 Repli temporaire pour le filtre Tag/Techno : On fait une recherche textuelle 
        // dans la description ou le titre au lieu d'une jointure d'entité manquante.
        if (!empty($filters['tag'])) {
            $qb->andWhere('p.title LIKE :tagSearch OR p.description LIKE :tagSearch')
               ->setParameter('tagSearch', '%' . $filters['tag'] . '%');
        }

        if (!empty($filters['team_size'])) {
            switch ($filters['team_size']) {
                case 'solo':
                    $qb->andWhere('SIZE(p.collaborators) = 0'); // cite: 7
                    break;
                case 'small':
                    $qb->andWhere('SIZE(p.collaborators) BETWEEN 1 AND 3'); // cite: 7
                    break;
                case 'large':
                    $qb->andWhere('SIZE(p.collaborators) > 3'); // cite: 7
                    break;
            }
        }

        if (!empty($filters['orphan'])) {
            switch ($filters['orphan']) {
                case 'no_client':
                    $qb->andWhere('p.owner IS NULL'); // cite: 7
                    break;
                case 'no_team':
                    $qb->andWhere('SIZE(p.collaborators) = 0'); // cite: 7
                    break;
            }
        }

        // 5. QUALITÉ & COMPLÉTION (AVANCEMENT)
        if (isset($filters['progress_min']) && $filters['progress_min'] !== '') {
            $qb->andWhere('p.progress >= :progress_min')
               ->setParameter('progress_min', (int) $filters['progress_min']); // cite: 7
        }

        if (isset($filters['progress_max']) && $filters['progress_max'] !== '') {
            $qb->andWhere('p.progress <= :progress_max')
               ->setParameter('progress_max', (int) $filters['progress_max']); // cite: 7
        }

        // 📌 Tri dynamique sécurisé
        $allowedSortFields = ['title', 'createdAt', 'status', 'budget', 'deadline', 'progress']; // cite: 6, 7
        $sortField = in_array($filters['sort'] ?? 'createdAt', $allowedSortFields, true) ? $filters['sort'] : 'createdAt'; // cite: 6, 7
        $direction = strtoupper($filters['direction'] ?? 'DESC'); // cite: 6, 7

        $qb->orderBy('p.' . $sortField, $direction); // cite: 6, 7

        // 📌 Application des tranches de pagination
        if (isset($filters['limit']) && isset($filters['page'])) {
            $limit = (int) $filters['limit'];
            $page = (int) $filters['page'];

            $qb->setMaxResults($limit)
               ->setFirstResult(($page - 1) * $limit); // cite: 6, 7
        }

        return $qb->getQuery()->getResult(); // cite: 6, 7
    }

    /**
     * 📌 Récupère les projets par statut.
     */
    public function findByStatus(ProjectStatusEnum $status): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', $status)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult() ?? []; // cite: 6
    }

    /**
     * 📌 Compte les projets par statut.
     * Retourne un tableau associatif : [status => count].
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('p.status, COUNT(p.id) AS count')
            ->groupBy('p.status')
            ->getQuery()
            ->getResult(); // cite: 6

        $counts = []; // cite: 6
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['count']; // cite: 6
        }

        // Initialiser tous les statuts à 0
        foreach (ProjectStatusEnum::cases() as $status) {
            if (!isset($counts[$status->value])) {
                $counts[$status->value] = 0; // cite: 6
            }
        }

        return $counts; // cite: 6
    }

    /**
     * 📌 Récupère les projets dont le budget est dépassé.
     */
    public function findOverBudget(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.spent > p.budget')
            ->orderBy('p.spent', 'DESC')
            ->getQuery()
            ->getResult() ?? []; // cite: 6
    }

    /**
     * 📌 Récupère les projets en cours avec un budget restant faible.
     */
    public function findLowBudgetRemaining(float $threshold = 100.00): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('(p.budget - p.spent) < :threshold')
            ->setParameter('status', ProjectStatusEnum::IN_PROGRESS)
            ->setParameter('threshold', $threshold)
            ->orderBy('p.budget - p.spent', 'ASC')
            ->getQuery()
            ->getResult() ?? []; // cite: 6
    }

    /**
     * 📌 Statistiques budgétaires globales.
     */
    public function getBudgetStatistics(): array
    {
        $query = $this->createQueryBuilder('p')
            ->select('SUM(p.budget) AS totalBudget', 'SUM(p.spent) AS totalSpent')
            ->getQuery()
            ->getSingleResult(); // cite: 6

        $totalBudget = (float) ($query['totalBudget'] ?? 0); // cite: 6
        $totalSpent = (float) ($query['totalSpent'] ?? 0); // cite: 6

        return [
            'totalBudget' => $totalBudget,
            'totalSpent' => $totalSpent,
            'remaining' => $totalBudget - $totalSpent,
        ]; // cite: 6
    }

    /**
     * 📌 Récupère les projets récents avec leur historique.
     */
    public function findRecentWithHistory(int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.histories', 'h')
            ->addSelect('h')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult() ?? []; // cite: 6
    }
}