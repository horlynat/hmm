<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\TimeEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeEntry>
 */
class TimeEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeEntry::class);
    }

    /**
     * Total des minutes saisies par utilisateur pour un projet.
     *
     * @return array<int, array{id:int,name:string|null,email:string,minutes:int}>
     */
    public function minutesByUserForProject(Project $project): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->createQueryBuilder('t')
            ->select('u.id AS id', 'u.fullName AS name', 'u.email AS email', 'SUM(t.minutes) AS minutes')
            ->join('t.user', 'u')
            ->where('t.project = :project')
            ->setParameter('project', $project)
            ->groupBy('u.id')
            ->addGroupBy('u.fullName')
            ->addGroupBy('u.email')
            ->orderBy('minutes', 'DESC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'name' => null !== $r['name'] ? (string) $r['name'] : null,
            'email' => (string) $r['email'],
            'minutes' => (int) $r['minutes'],
        ], $rows);
    }
}
