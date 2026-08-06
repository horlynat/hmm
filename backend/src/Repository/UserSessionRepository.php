<?php

namespace App\Repository;

use App\Entity\UserSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSession>
 */
class UserSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSession::class);
    }

    /**
     * @return UserSession[]
     */
    public function findAllOrderedByCreatedAt(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.user', 'u')
            ->addSelect('u')
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneBySessionId(string $sessionId): ?UserSession
    {
        return $this->findOneBy(['sessionId' => $sessionId]);
    }
}
