<?php

namespace App\Repository;

use App\Entity\PermissionDefinition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PermissionDefinition>
 */
class PermissionDefinitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PermissionDefinition::class);
    }

    public function findOneByCode(string $code): ?PermissionDefinition
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * Tout le catalogue, trié pour un affichage stable groupe par groupe —
     * consommé par PermissionRegistry (mis en cache, cf. son docblock) et par
     * AdminSecurityPermissionController (page d'édition).
     *
     * @return PermissionDefinition[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.category', 'ASC')
            ->addOrderBy('p.code', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
