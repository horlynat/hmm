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

    public function countRecentByEmail(string $email, \DateInterval $window): int
    {
        $since = (new \DateTimeImmutable())->sub($window);

        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.email = :email')
            ->andWhere('f.createdAt >= :since')
            ->setParameter('email', $email)
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

    /**
     * @return FailedLoginAttempt[]
     */
    public function findRecentByIp(string $ip, int $limit = 10, ?int $excludeId = null): array
    {
        $queryBuilder = $this->createQueryBuilder('f')
            ->andWhere('f.ip = :ip')
            ->setParameter('ip', $ip)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit);

        if (null !== $excludeId) {
            $queryBuilder->andWhere('f.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return FailedLoginAttempt[]
     */
    public function findRecentByEmail(string $email, int $limit = 10, ?int $excludeId = null): array
    {
        $queryBuilder = $this->createQueryBuilder('f')
            ->andWhere('f.email = :email')
            ->setParameter('email', $email)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit);

        if (null !== $excludeId) {
            $queryBuilder->andWhere('f.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Nombre de lignes purgeables (sans les supprimer) — affiché sur le
     * bouton de purge manuelle pour que l'admin sache ce qu'il s'apprête à
     * faire avant de confirmer.
     */
    public function countOlderThan(\DateTimeImmutable $threshold): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.createdAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Purge RGPD/volumétrie — cf. SecurityLogRetentionPolicy pour la durée de
     * rétention retenue et sa justification. Bulk DELETE (DQL), pas de flush
     * requis côté appelant.
     */
    public function deleteOlderThan(\DateTimeImmutable $threshold): int
    {
        return $this->createQueryBuilder('f')
            ->delete()
            ->where('f.createdAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }
}
