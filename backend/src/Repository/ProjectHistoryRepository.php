<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\ProjectHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour gérer l'historique des projets.
 */
class ProjectHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectHistory::class);
    }

    /**
     * 📌 Récupère l'historique complet d'un projet trié par date (du plus récent au plus ancien).
     */
    public function findByProjectOrderedByDate(Project $project): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.project = :project')
            ->setParameter('project', $project)
            ->orderBy('h.createdAt', 'DESC')
            ->getQuery()
            ->getResult() ?? [];
    }

    /**
     * 📌 Récupère les dernières actions pour un projet (limité).
     */
    public function findRecentByProject(Project $project, int $limit = 10): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.project = :project')
            ->setParameter('project', $project)
            ->orderBy('h.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult() ?? [];
    }

    /**
     * 📌 Récupère l'historique d'un projet pour une action spécifique.
     */
    public function findByProjectAndAction(Project $project, string $action): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.project = :project')
            ->andWhere('h.action = :action')
            ->setParameter('project', $project)
            ->setParameter('action', $action)
            ->orderBy('h.createdAt', 'DESC')
            ->getQuery()
            ->getResult() ?? [];
    }

    /**
     * 📌 Compte le nombre d'actions par type pour un projet.
     * Retourne un tableau associatif : [action => count].
     */
    public function countByAction(Project $project): array
    {
        $result = $this->createQueryBuilder('h')
            ->select('h.action, COUNT(h.id) AS count')
            ->andWhere('h.project = :project')
            ->setParameter('project', $project)
            ->groupBy('h.action')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['action']] = (int) $row['count'];
        }

        return $counts;
    }
}
