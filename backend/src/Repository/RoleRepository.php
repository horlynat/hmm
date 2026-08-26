<?php

namespace App\Repository;

use App\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    public function findOneByCode(string $code): ?Role
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * @return Role[]
     */
    public function findAllOrderedByRank(): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.rank', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
