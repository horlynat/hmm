<?php

namespace App\Repository;

use App\Entity\SupportTicket;
use App\Enum\SupportTicketStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SupportTicket>
 */
class SupportTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupportTicket::class);
    }

    /**
     * @return SupportTicket[]
     */
    public function findByStatus(SupportTicketStatusEnum $status): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->setParameter('status', $status)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function countByStatus(SupportTicketStatusEnum $status): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function countOpenOrInProgress(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('statuses', [SupportTicketStatusEnum::OPEN, SupportTicketStatusEnum::IN_PROGRESS])
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function findOneByAccessToken(string $token): ?SupportTicket
    {
        return $this->findOneBy(['accessToken' => $token]);
    }
}
