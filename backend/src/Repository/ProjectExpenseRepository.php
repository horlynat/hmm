<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\ProjectExpense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour gérer les dépenses liées aux projets.
 */
class ProjectExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectExpense::class);
    }

    /**
     * 📌 Récupère les dépenses d'un projet triées par date (du plus récent au plus ancien).
     */
    public function findByProjectOrderedByDate(Project $project): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.project = :project')
            ->setParameter('project', $project)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult() ?? [];
    }

    /**
     * 📌 Récupère les dépenses d'un projet pour une période donnée.
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
            ->getResult() ?? [];
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
     */
    public function findOverBudgetProjects(): array
    {
        return $this->createQueryBuilder('e')
            ->select('p.id, p.name, p.budget, SUM(e.amount) AS totalSpent')
            ->join('e.project', 'p')
            ->groupBy('p.id')
            ->having('SUM(e.amount) > p.budget')
            ->getQuery()
            ->getResult() ?? [];
    }

    /**
     * 📌 Récupère les projets avec un budget restant faible (< seuil).
     */
    public function findLowBudgetRemainingProjects(float $threshold = 0.1): array
    {
        return $this->createQueryBuilder('e')
            ->select('p.id, p.name, p.budget, SUM(e.amount) AS totalSpent')
            ->join('e.project', 'p')
            ->groupBy('p.id')
            ->having('(p.budget - SUM(e.amount)) / p.budget < :threshold')
            ->andHaving('p.budget > 0')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult() ?? [];
    }

    /**
     * 🔎 Recherche de dépenses avec filtres dynamiques.
     *
     * @param array $filters
     *   - project : filtre par projet (Project)
     *   - min     : montant minimum
     *   - max     : montant maximum
     *   - start   : date de début
     *   - end     : date de fin
     */
    public function findByFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('e')
            ->join('e.project', 'p')
            ->addSelect('p');

        if (!empty($filters['project'])) {
            $qb->andWhere('e.project = :project')
               ->setParameter('project', $filters['project']);
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

        return $qb->orderBy('e.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult() ?? [];
    }
}
