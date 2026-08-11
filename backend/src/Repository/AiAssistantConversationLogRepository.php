<?php

namespace App\Repository;

use App\Entity\AiAssistantConversationLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiAssistantConversationLog>
 */
class AiAssistantConversationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiAssistantConversationLog::class);
    }

    /**
     * Somme des coûts (USD) enregistrés depuis le 1er du mois en cours —
     * utilisée par App\Service\AiAssistantBudgetGuard pour la coupure
     * automatique dès dépassement de ASSISTANT_MONTHLY_BUDGET_USD.
     */
    public function sumCostThisMonth(): float
    {
        $startOfMonth = new \DateTimeImmutable('first day of this month 00:00:00');

        $result = $this->createQueryBuilder('l')
            ->select('SUM(l.costUsd) as total')
            ->where('l.createdAt >= :start')
            ->setParameter('start', $startOfMonth)
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $result ? (float) $result : 0.0;
    }

    public function deleteOlderThan(\DateTimeImmutable $threshold): int
    {
        return $this->createQueryBuilder('l')
            ->delete()
            ->where('l.createdAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }
}
