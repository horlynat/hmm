<?php

namespace App\Repository;

use App\Entity\BlockedIp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlockedIp>
 */
class BlockedIpRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlockedIp::class);
    }

    /**
     * Point d'entrée unique consulté par IpBlockSubscriber à chaque tentative
     * de connexion — doit rester rapide (indexé sur `ip`, une seule ligne
     * possible grâce à la contrainte unique de l'entité).
     */
    public function isBlocked(string $ip): bool
    {
        $blockedIp = $this->findOneBy(['ip' => $ip]);

        return null !== $blockedIp && !$blockedIp->isExpired();
    }

    public function findOneByIp(string $ip): ?BlockedIp
    {
        return $this->findOneBy(['ip' => $ip]);
    }

    /**
     * @return BlockedIp[]
     */
    public function findAllOrderedByCreatedAt(): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
