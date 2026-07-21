<?php

namespace App\Repository;

use App\Entity\QuoteRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuoteRequest>
 */
class QuoteRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuoteRequest::class);
    }

    /**
     * @return QuoteRequest[]
     */
    public function findByStatus(?bool $status): array
    {
        $queryBuilder = $this->createQueryBuilder('q');

        if (null === $status) {
            $queryBuilder->andWhere('q.status IS NULL');
        } else {
            $queryBuilder->andWhere('q.status = :status')
                ->setParameter('status', $status);
        }

        return $queryBuilder
            ->orderBy('q.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    //    /**
    //     * @return QuoteRequest[] Returns an array of QuoteRequest objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('q')
    //            ->andWhere('q.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('q.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?QuoteRequest
    //    {
    //        return $this->createQueryBuilder('q')
    //            ->andWhere('q.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
