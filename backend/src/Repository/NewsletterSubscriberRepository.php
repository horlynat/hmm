<?php

namespace App\Repository;

use App\Entity\NewsletterSubscriber;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NewsletterSubscriber>
 */
class NewsletterSubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterSubscriber::class);
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.unsubscribedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Destinataires d'une notification de nouveau contenu — confirmés
     * (double opt-in) ET non désinscrits. cf. App\Service\NewsletterNotifier.
     *
     * @return NewsletterSubscriber[]
     */
    public function findActiveConfirmed(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.confirmedAt IS NOT NULL')
            ->andWhere('s.unsubscribedAt IS NULL')
            ->getQuery()
            ->getResult()
        ;
    }
}
