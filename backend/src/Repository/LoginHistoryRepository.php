<?php

namespace App\Repository;

use App\Entity\LoginHistory;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoginHistory>
 */
class LoginHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginHistory::class);
    }

    /**
     * Même principe que FailedLoginAttemptRepository::countSince() — sert les
     * tuiles KPI du journal des connexions (AdminSecurityLogController).
     */
    public function countSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.loginAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countDistinctUsersSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(DISTINCT l.user)')
            ->andWhere('l.loginAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return LoginHistory[]
     */
    public function findRecentByUser(User $user, int $limit = 10, ?int $excludeId = null): array
    {
        $queryBuilder = $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.loginAt', 'DESC')
            ->setMaxResults($limit);

        if (null !== $excludeId) {
            $queryBuilder->andWhere('l.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Connexions réussies pour un email donné — utilisé par la vue détail
     * d'une tentative échouée (FailedLoginAttempt n'a pas de relation vers
     * User : l'email tenté peut ne correspondre à aucun compte réel) pour
     * situer la tentative dans le contexte du compte visé, si celui-ci existe.
     *
     * @return LoginHistory[]
     */
    public function findRecentByEmail(string $email, int $limit = 10): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.user', 'u')
            ->andWhere('u.email = :email')
            ->setParameter('email', $email)
            ->orderBy('l.loginAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Nombre de lignes purgeables (sans les supprimer) — affiché sur le
     * bouton de purge manuelle pour que l'admin sache ce qu'il s'apprête à
     * faire avant de confirmer.
     */
    public function countOlderThan(\DateTimeImmutable $threshold): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.loginAt < :threshold')
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
        return $this->createQueryBuilder('l')
            ->delete()
            ->where('l.loginAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }
}
