<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\ProjectExpense;
use App\Enum\ExpenseStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour gérer les dépenses liées aux projets.
 *
 * @extends ServiceEntityRepository<ProjectExpense>
 */
class ProjectExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectExpense::class);
    }

    /**
     * 📌 Récupère les dépenses d'un projet triées par date (du plus récent au plus ancien).
     *
     * @return ProjectExpense[]
     */
    public function findByProjectOrderedByDate(Project $project): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.project = :project')
            ->setParameter('project', $project)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 📌 Récupère les dépenses d'un projet pour une période donnée.
     *
     * @return ProjectExpense[]
     */
    public function findByProjectAndDateRange(Project $project, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.project = :project')
            ->andWhere('e.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('project', $project)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 📌 Récupère le total des dépenses pour un projet.
     */
    public function getTotalByProject(Project $project): float
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.amount) AS total')
            ->andWhere('e.project = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * 📌 Récupère les projets qui dépassent leur budget.
     *
     * @return array<int, array{id: int, title: string, budget: string, totalSpent: string}>
     */
    public function findOverBudgetProjects(): array
    {
        return $this->createQueryBuilder('e')
            ->select('p.id, p.title, p.budget, SUM(e.amount) AS totalSpent')
            ->join('e.project', 'p')
            ->groupBy('p.id')
            ->having('SUM(e.amount) > p.budget')
            ->getQuery()
            ->getResult();
    }

    /**
     * 📌 Récupère les projets avec un budget restant faible (< seuil).
     *
     * @return array<int, array{id: int, title: string, budget: string, totalSpent: string}>
     */
    public function findLowBudgetRemainingProjects(float $threshold = 0.1): array
    {
        return $this->createQueryBuilder('e')
            ->select('p.id, p.title, p.budget, SUM(e.amount) AS totalSpent')
            ->join('e.project', 'p')
            ->groupBy('p.id')
            ->having('(p.budget - SUM(e.amount)) / p.budget < :threshold')
            ->andHaving('p.budget > 0')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    /**
     * 🔎 Construit le QueryBuilder de recherche à filtres dynamiques, partagé
     * par findByFilters() (écran projet) et le module Finance (vue et export
     * tous projets confondus — voir AdminFinanceController).
     *
     * @param array<string, mixed> $filters
     *                                      - project  : filtre par projet (Project)
     *                                      - status   : filtre par statut (ExpenseStatusEnum)
     *                                      - category : filtre par catégorie (ExpenseCategoryEnum)
     *                                      - min      : montant minimum
     *                                      - max      : montant maximum
     *                                      - start    : date de début
     *                                      - end      : date de fin
     */
    public function createFilteredQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e')
            ->join('e.project', 'p')
            ->addSelect('p');

        if (!empty($filters['project'])) {
            $qb->andWhere('e.project = :project')
               ->setParameter('project', $filters['project']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['category'])) {
            $qb->andWhere('e.category = :category')
               ->setParameter('category', $filters['category']);
        }

        if (!empty($filters['min'])) {
            $qb->andWhere('e.amount >= :min')
               ->setParameter('min', $filters['min']);
        }

        if (!empty($filters['max'])) {
            $qb->andWhere('e.amount <= :max')
               ->setParameter('max', $filters['max']);
        }

        if (!empty($filters['start']) && !empty($filters['end'])) {
            $qb->andWhere('e.createdAt BETWEEN :start AND :end')
               ->setParameter('start', $filters['start'])
               ->setParameter('end', $filters['end']);
        }

        return $qb;
    }

    /**
     * 🔎 Recherche de dépenses avec filtres dynamiques (voir createFilteredQueryBuilder()).
     *
     * @param array<string, mixed> $filters
     *
     * @return ProjectExpense[]
     */
    public function findByFilters(array $filters = []): array
    {
        return $this->createFilteredQueryBuilder($filters)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Totaux des dépenses approuvées, groupés par projet — seules les
     * dépenses APPROVED impactent réellement le budget consommé
     * (cf. docblock d'ExpenseStatusEnum). Montants déjà en EUR
     * (CurrencyConversionService::PROJECT_LEDGER_CURRENCY), aucune
     * conversion nécessaire ici contrairement aux factures.
     *
     * @return array<int, string> total approuvé indexé par id de projet
     */
    public function getApprovedTotalsByProject(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.project) AS projectId', 'SUM(e.amount) AS total')
            ->andWhere('e.status = :status')
            ->setParameter('status', ExpenseStatusEnum::APPROVED)
            ->groupBy('e.project')
            ->getQuery()
            ->getArrayResult();

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row['projectId']] = $row['total'];
        }

        return $totals;
    }
}
