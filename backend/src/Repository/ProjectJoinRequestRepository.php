<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\ProjectJoinRequest;
use App\Entity\User;
use App\Enum\ProjectJoinRequestStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectJoinRequest>
 */
class ProjectJoinRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectJoinRequest::class);
    }

    /** Demande active (en attente) d'un freelance sur un projet donné, s'il en existe une. */
    public function findPendingFor(Project $project, User $user): ?ProjectJoinRequest
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.project = :project')
            ->andWhere('r.user = :user')
            ->andWhere('r.status = :status')
            ->setParameter('project', $project)
            ->setParameter('user', $user)
            ->setParameter('status', ProjectJoinRequestStatusEnum::PENDING)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array<int, int> Identifiants des projets sur lesquels l'utilisateur a une demande en attente.
     */
    public function findPendingProjectIdsFor(User $user): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.project) AS projectId')
            ->andWhere('r.user = :user')
            ->andWhere('r.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', ProjectJoinRequestStatusEnum::PENDING)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['projectId'], $rows);
    }

    /**
     * @return ProjectJoinRequest[]
     */
    public function findPendingForProject(Project $project): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.project = :project')
            ->andWhere('r.status = :status')
            ->setParameter('project', $project)
            ->setParameter('status', ProjectJoinRequestStatusEnum::PENDING)
            ->leftJoin('r.user', 'u')
            ->addSelect('u')
            ->orderBy('r.requestedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Historique complet (tous statuts) des demandes d'un freelance, du plus
     * récent au plus ancien — pour l'onglet « Mes demandes » du hub
     * "Gestion de projet" (App\Controller\Api\MeController::readMyJoinRequests).
     * Contrairement à findPendingProjectIdsFor(), inclut aussi les demandes
     * déjà tranchées (approuvées/refusées) : le freelance doit voir le
     * dénouement de ce qu'il a envoyé, pas seulement ce qui reste en attente.
     *
     * @return ProjectJoinRequest[]
     */
    public function findAllForUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->leftJoin('r.project', 'p')
            ->addSelect('p')
            ->orderBy('r.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Toutes les demandes en attente, tous projets confondus — pour l'espace
     * admin dédié (AdminProjectController::freelanceSpace).
     *
     * @return ProjectJoinRequest[]
     */
    public function findAllPending(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->setParameter('status', ProjectJoinRequestStatusEnum::PENDING)
            ->leftJoin('r.user', 'u')
            ->addSelect('u')
            ->leftJoin('r.project', 'p')
            ->addSelect('p')
            ->orderBy('r.requestedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
