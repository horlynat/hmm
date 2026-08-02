<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ExpenseStatusEnum;
use App\Enum\ProjectStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour gérer les projets et leurs statistiques.
 *
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class); // cite: 6
    }

    /**
     * 🔎 Recherche ultra-avancée globale de projets avec filtres dynamiques, tri et pagination.
     *
     * @param array<string, mixed> $filters
     * @return Project[]
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
                       ->setParameter('statusDone', ProjectStatusEnum::COMPLETED); // cite: 7
                    break;
                case 'imminent': // Échéance sous 7 jours
                    $limit = (clone $now)->modify('+7 days');
                    $qb->andWhere('p.deadline BETWEEN :now AND :limit')
                       ->andWhere('p.status != :statusDone')
                       ->setParameter('now', $now)
                       ->setParameter('limit', $limit)
                       ->setParameter('statusDone', ProjectStatusEnum::COMPLETED); // cite: 7
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
                       ->setParameter('statusDone', ProjectStatusEnum::COMPLETED); // cite: 7
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
     *
     * @return Project[]
     */
    public function findByStatus(ProjectStatusEnum $status): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', $status)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult(); // cite: 6
    }

    /**
     * 📌 Compte les projets par statut.
     * Retourne un tableau associatif : [status => count].
     *
     * @return array<string, int>
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
            $counts[$row['status']->value] = (int) $row['count']; // cite: 6
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
     *
     * @return Project[]
     */
    public function findOverBudget(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.spent > p.budget')
            ->orderBy('p.spent', 'DESC')
            ->getQuery()
            ->getResult(); // cite: 6
    }

    /**
     * 📌 Récupère les projets en cours avec un budget restant faible.
     *
     * @return Project[]
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
            ->getResult(); // cite: 6
    }

    /**
     * 📌 Statistiques budgétaires globales.
     *
     * @return array{totalBudget: float, totalSpent: float, remaining: float}
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
     *
     * @return Project[]
     */
    public function findRecentWithHistory(int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.histories', 'h')
            ->addSelect('h')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult(); // cite: 6
    }

    /**
     * 👤 Projets d'une partie prenante : ceux dont elle est le client, le
     * responsable (owner) OU un collaborateur.
     *
     * @return Project[]
     */
    public function findForStakeholder(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.collaborators', 'c')
            ->where('p.client = :user OR p.owner = :user OR c = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->distinct()
            ->getQuery()
            ->getResult();
    }

    /**
     * 📊 Agrégats globaux pour la centrale de gestion (une seule requête).
     * Compte actif/terminé, dépassements, retards, échéances proches, orphelins,
     * et totaux budgétaires du portefeuille.
     *
     * @return array{total:int,active:int,completed:int,overBudget:int,lowBudget:int,overdue:int,dueThisWeek:int,noClient:int,noTeam:int,totalBudget:string,totalSpent:string,remainingBudget:string}
     */
    public function getCommandCenterStats(): array
    {
        $now = new \DateTimeImmutable('today');
        $closed = [ProjectStatusEnum::COMPLETED->value, ProjectStatusEnum::SUSPENDED->value];

        /** @var array<string, mixed> $row */
        $row = $this->createQueryBuilder('p')
            ->select(
                'COUNT(p.id) AS total',
                'SUM(p.budget) AS totalBudget',
                'SUM(p.spent) AS totalSpent',
                'SUM(CASE WHEN p.status NOT IN (:closed) THEN 1 ELSE 0 END) AS active',
                'SUM(CASE WHEN p.status = :completed THEN 1 ELSE 0 END) AS completed',
                'SUM(CASE WHEN p.spent > p.budget THEN 1 ELSE 0 END) AS overBudget',
                'SUM(CASE WHEN p.budget > 0 AND p.spent <= p.budget AND (p.budget - p.spent) / p.budget < 0.1 THEN 1 ELSE 0 END) AS lowBudget',
                'SUM(CASE WHEN p.deadline IS NOT NULL AND p.deadline < :now AND p.status NOT IN (:closed) THEN 1 ELSE 0 END) AS overdue',
                'SUM(CASE WHEN p.deadline IS NOT NULL AND p.deadline >= :now AND p.deadline <= :weekEnd AND p.status NOT IN (:closed) THEN 1 ELSE 0 END) AS dueThisWeek',
                'SUM(CASE WHEN p.client IS NULL THEN 1 ELSE 0 END) AS noClient',
            )
            ->setParameter('closed', $closed)
            ->setParameter('completed', ProjectStatusEnum::COMPLETED->value)
            ->setParameter('now', $now)
            ->setParameter('weekEnd', $now->modify('+7 days'))
            ->getQuery()
            ->getSingleResult();

        $totalBudget = (string) ($row['totalBudget'] ?? '0');
        $totalSpent = (string) ($row['totalSpent'] ?? '0');
        if ('' === $totalBudget) { $totalBudget = '0'; }
        if ('' === $totalSpent) { $totalSpent = '0'; }

        // Projets sans aucun collaborateur (staff) : une ligne par projet à 0 collaborateur.
        $noTeam = \count(
            $this->createQueryBuilder('p')
                ->select('p.id')
                ->leftJoin('p.collaborators', 'c')
                ->groupBy('p.id')
                ->having('COUNT(c.id) = 0')
                ->getQuery()
                ->getScalarResult()
        );

        return [
            'total' => (int) $row['total'],
            'active' => (int) $row['active'],
            'completed' => (int) $row['completed'],
            'overBudget' => (int) $row['overBudget'],
            'lowBudget' => (int) $row['lowBudget'],
            'overdue' => (int) $row['overdue'],
            'dueThisWeek' => (int) $row['dueThisWeek'],
            'noClient' => (int) $row['noClient'],
            'noTeam' => $noTeam,
            'totalBudget' => $totalBudget,
            'totalSpent' => $totalSpent,
            'remainingBudget' => bcsub($totalBudget, $totalSpent, 2),
        ];
    }

    /**
     * 💸 Synthèse des dépenses en attente d'approbation sur tout le portefeuille.
     *
     * @return array{count:int,total:string}
     */
    public function getPendingApprovalsSummary(): array
    {
        /** @var array<string, mixed> $row */
        $row = $this->getEntityManager()
            ->createQuery(
                'SELECT COUNT(e.id) AS cnt, COALESCE(SUM(e.amount), 0) AS total
                 FROM App\Entity\ProjectExpense e
                 WHERE e.status = :pending'
            )
            ->setParameter('pending', ExpenseStatusEnum::PENDING->value)
            ->getSingleResult();

        return [
            'count' => (int) ($row['cnt'] ?? 0),
            'total' => (string) ($row['total'] ?? '0'),
        ];
    }

    /**
     * 🎯 Répartition des projets par priorité (clé = valeur d'enum ou '' si nulle).
     *
     * @return array<string, int>
     */
    public function countByPriority(): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('p.priority AS priority', 'COUNT(p.id) AS cnt')
            ->groupBy('p.priority')
            ->getQuery()
            ->getScalarResult();

        $out = [];
        foreach ($rows as $r) {
            $out[(string) ($r['priority'] ?? '')] = (int) $r['cnt'];
        }

        return $out;
    }

    /**
     * ⏰ Prochaines échéances (projets actifs dont l'échéance tombe dans `$days` jours).
     *
     * @return Project[]
     */
    public function findUpcomingDeadlines(int $days = 14, int $limit = 6): array
    {
        $now = new \DateTimeImmutable('today');

        return $this->createQueryBuilder('p')
            ->where('p.deadline IS NOT NULL')
            ->andWhere('p.deadline >= :now')
            ->andWhere('p.deadline <= :until')
            ->andWhere('p.status NOT IN (:closed)')
            ->setParameter('now', $now)
            ->setParameter('until', $now->modify('+'.$days.' days'))
            ->setParameter('closed', [ProjectStatusEnum::COMPLETED->value, ProjectStatusEnum::SUSPENDED->value])
            ->orderBy('p.deadline', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 👥 Charge d'équipe : nombre de projets actifs par collaborateur (top N).
     *
     * @return array<int, array{id:int,name:string|null,email:string,cnt:int}>
     */
    public function countActiveProjectsByCollaborator(int $limit = 5): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('u.id AS id', 'u.fullName AS name', 'u.email AS email', 'COUNT(p.id) AS cnt')
            ->join('p.collaborators', 'u')
            ->where('p.status NOT IN (:closed)')
            ->setParameter('closed', [ProjectStatusEnum::COMPLETED->value, ProjectStatusEnum::SUSPENDED->value])
            ->groupBy('u.id')
            ->addGroupBy('u.fullName')
            ->addGroupBy('u.email')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'name' => null !== $r['name'] ? (string) $r['name'] : null,
            'email' => (string) $r['email'],
            'cnt' => (int) $r['cnt'],
        ], $rows);
    }
}