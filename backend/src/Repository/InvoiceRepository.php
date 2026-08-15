<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Enum\InvoiceStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    /**
     * 🔎 Construit le QueryBuilder de recherche à filtres dynamiques, tous
     * projets confondus — sert la vue et l'export du module Finance (voir
     * AdminFinanceController).
     *
     * @param array<string, mixed> $filters
     *                                      - status   : filtre par statut (InvoiceStatusEnum)
     *                                      - project  : filtre par projet (Project)
     *                                      - client   : filtre par client (User)
     *                                      - currency : filtre par devise (code ISO, tel que saisi sur la facture)
     *                                      - start    : date d'émission de début
     *                                      - end      : date d'émission de fin
     */
    public function createFilteredQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i')
            ->join('i.project', 'p')->addSelect('p')
            ->leftJoin('p.client', 'c')->addSelect('c');

        if (!empty($filters['status'])) {
            $qb->andWhere('i.status = :status')->setParameter('status', $filters['status']);
        }

        if (!empty($filters['project'])) {
            $qb->andWhere('i.project = :project')->setParameter('project', $filters['project']);
        }

        if (!empty($filters['client'])) {
            $qb->andWhere('c = :client')->setParameter('client', $filters['client']);
        }

        if (!empty($filters['currency'])) {
            $qb->andWhere('i.currency = :currency')->setParameter('currency', $filters['currency']);
        }

        if (!empty($filters['start']) && !empty($filters['end'])) {
            $qb->andWhere('i.issuedAt BETWEEN :start AND :end')
               ->setParameter('start', $filters['start'])
               ->setParameter('end', $filters['end']);
        }

        return $qb;
    }

    /**
     * Devises réellement utilisées par au moins une facture, pour peupler le
     * filtre de la vue Finance sans dépendre de CurrencyEnum (dont le
     * périmètre — USD/EUR/XAF — est plus restreint que ce qu'InvoiceType
     * accepte réellement en saisie).
     *
     * @return string[]
     */
    public function findDistinctCurrencies(): array
    {
        return array_column(
            $this->createQueryBuilder('i')
                ->select('DISTINCT i.currency')
                ->orderBy('i.currency', 'ASC')
                ->getQuery()
                ->getScalarResult(),
            'currency',
        );
    }
}
