<?php

namespace App\Repository;

use App\Entity\AiAssistantEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiAssistantEntry>
 */
class AiAssistantEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiAssistantEntry::class);
    }

    /**
     * @return AiAssistantEntry[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
