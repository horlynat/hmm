<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Enum\InvoiceStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    /**
     * Factures en attente, échéance dépassée, jamais relancées ou relancées
     * il y a plus de 7 jours — évite de spammer le client à chaque exécution
     * de la commande (voir App\Command\RemindOverdueInvoicesCommand).
     *
     * @return Invoice[]
     */
    public function findOverdueForReminder(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status = :status')
            ->andWhere('i.dueDate < :today')
            ->andWhere('i.reminderSentAt IS NULL OR i.reminderSentAt < :throttle')
            ->setParameter('status', InvoiceStatusEnum::PENDING)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->setParameter('throttle', new \DateTimeImmutable('-7 days'))
            ->orderBy('i.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
