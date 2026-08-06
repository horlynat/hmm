<?php

namespace App\Repository;

use App\Entity\FailedLoginAttempt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FailedLoginAttempt>
 */
class FailedLoginAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FailedLoginAttempt::class);
    }

    /**
     * @return FailedLoginAttempt[]
     */
    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countRecentByIp(string $ip, \DateInterval $window): int
    {
        $since = (new \DateTimeImmutable())->sub($window);

        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.ip = :ip')
            ->andWhere('f.createdAt >= :since')
            ->setParameter('ip', $ip)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * IPs ayant échoué plusieurs fois récemment, groupées et comptées.
     *
     * @return array<int, array{ip: string, count: int, lastAttempt: \DateTimeImmutable}>
     */
    public function findSuspiciousIps(\DateInterval $window, int $minAttempts = 3): array
    {
        $since = (new \DateTimeImmutable())->sub($window);

        $rows = $this->createQueryBuilder('f')
            ->select('f.ip AS ip', 'COUNT(f.id) AS count', 'MAX(f.createdAt) AS lastAttempt')
            ->andWhere('f.createdAt >= :since')
            ->andWhere('f.ip IS NOT NULL')
            ->groupBy('f.ip')
            ->having('COUNT(f.id) >= :minAttempts')
            ->orderBy('count', 'DESC')
            ->setParameter('since', $since)
            ->setParameter('minAttempts', $minAttempts)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row): array => [
                'ip' => $row['ip'],
                'count' => (int) $row['count'],
                'lastAttempt' => new \DateTimeImmutable((string) $row['lastAttempt']),
            ],
            $rows
        );
    }
}
