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
     *                                      - status   : filtre par statut (InvoiceStatusEnum) — ignoré si `overdue` est présent
     *                                      - overdue  : si vrai (ou tronqué), remplace `status` par la même condition qu'Invoice::isOverdue() (pending + échéance dépassée), impossible à exprimer en appelant la méthode PHP depuis un WHERE
     *                                      - project  : filtre par projet (Project)
     *                                      - client   : filtre par client (User)
     *                                      - currency : filtre par devise (code ISO, tel que saisi sur la facture)
     *                                      - search   : recherche libre sur le numéro, le titre du projet, le nom/email du client
     *                                      - min      : montant minimum
     *                                      - max      : montant maximum
     *                                      - start    : date d'émission de début
     *                                      - end      : date d'émission de fin
     */
    public function createFilteredQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i')
            ->join('i.project', 'p')->addSelect('p')
            ->leftJoin('p.client', 'c')->addSelect('c');

        if (!empty($filters['overdue'])) {
            $qb->andWhere('i.status = :status')
               ->andWhere('i.dueDate < :today')
               ->setParameter('status', InvoiceStatusEnum::PENDING)
               ->setParameter('today', new \DateTimeImmutable('today'));
        } elseif (!empty($filters['status'])) {
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

        if (!empty($filters['search'])) {
            $qb->andWhere('i.number LIKE :search OR p.title LIKE :search OR c.fullName LIKE :search OR c.email LIKE :search')
               ->setParameter('search', '%'.$filters['search'].'%');
        }

        if (!empty($filters['min'])) {
            $qb->andWhere('i.amount >= :min')->setParameter('min', $filters['min']);
        }

        if (!empty($filters['max'])) {
            $qb->andWhere('i.amount <= :max')->setParameter('max', $filters['max']);
        }

        if (!empty($filters['start']) && !empty($filters['end'])) {
            $qb->andWhere('i.issuedAt BETWEEN :start AND :end')
               ->setParameter('start', $filters['start'])
               ->setParameter('end', $filters['end']);
        }

        return $qb;
    }

    /**
     * Compte de factures pour un jeu de filtres donné — sert les puces de
     * statut rapides de la vue Finance (Toutes / En attente / Payées / En
     * retard), calculées sur les MÊMES filtres que la liste (recherche,
     * projet, client…) sans le statut lui-même, pour rester justes quand
     * on combine une puce avec une recherche.
     *
     * @param array<string, mixed> $filters cf. createFilteredQueryBuilder()
     */
    public function countByFilters(array $filters = []): int
    {
        return (int) $this->createFilteredQueryBuilder($filters)
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();
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

    /**
     * Totaux des factures payées, groupés par projet puis par devise —
     * jamais sommés en une seule valeur ici (des devises différentes ne
     * s'additionnent pas en SQL) : la conversion vers une devise de
     * référence se fait ensuite côté appelant, comme dans
     * ProjectStatisticsService::getRevenueBreakdown().
     *
     * @return array<int, array{currency: string, total: string}[]> indexé par id de projet
     */
    public function getPaidTotalsByProjectAndCurrency(): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.project) AS projectId', 'i.currency AS currency', 'SUM(i.amount) AS total')
            ->andWhere('i.status = :status')
            ->setParameter('status', InvoiceStatusEnum::PAID)
            ->groupBy('i.project')
            ->addGroupBy('i.currency')
            ->getQuery()
            ->getArrayResult();

        $totalsByProject = [];
        foreach ($rows as $row) {
            $totalsByProject[(int) $row['projectId']][] = ['currency' => $row['currency'], 'total' => $row['total']];
        }

        return $totalsByProject;
    }

    /**
     * Totaux des factures payées, groupés par mois d'émission puis par
     * devise, sur les $months derniers mois glissants (même principe que
     * ProjectStatisticsService::getChartData()::expensesByMonth, mais sur le
     * revenu plutôt que la dépense).
     *
     * @return array<string, array{currency: string, total: string}[]> indexé par "Y-m"
     */
    public function getPaidTotalsByMonthAndCurrency(int $months): array
    {
        $start = (new \DateTimeImmutable('first day of this month'))->modify(sprintf('-%d months', $months - 1));

        $rows = $this->createQueryBuilder('i')
            ->select('SUBSTRING(i.paidAt, 1, 7) AS yearMonth', 'i.currency AS currency', 'SUM(i.amount) AS total')
            ->andWhere('i.status = :status')
            ->andWhere('i.paidAt >= :start')
            ->setParameter('status', InvoiceStatusEnum::PAID)
            ->setParameter('start', $start)
            ->groupBy('yearMonth')
            ->addGroupBy('i.currency')
            ->getQuery()
            ->getArrayResult();

        $totalsByMonth = [];
        foreach ($rows as $row) {
            $totalsByMonth[$row['yearMonth']][] = ['currency' => $row['currency'], 'total' => $row['total']];
        }

        return $totalsByMonth;
    }

    /**
     * Totaux des factures en attente, groupés par mois d'échéance puis par
     * devise, sur les $months prochains mois — sert de base à un
     * prévisionnel de trésorerie simple (ce qui est déjà dû, pas un modèle
     * prédictif : aucune facturation récurrente n'existe dans l'app).
     * Les échéances déjà passées ne sont pas comptées (voir Invoice::isOverdue()
     * pour ce cas, distinct d'une projection future).
     *
     * @return array<string, array{currency: string, total: string}[]> indexé par "Y-m"
     */
    public function getPendingTotalsByDueMonthAndCurrency(int $months): array
    {
        $today = new \DateTimeImmutable('today');
        $end = (new \DateTimeImmutable('first day of this month'))->modify(sprintf('+%d months', $months))->modify('-1 second');

        $rows = $this->createQueryBuilder('i')
            ->select('SUBSTRING(i.dueDate, 1, 7) AS yearMonth', 'i.currency AS currency', 'SUM(i.amount) AS total')
            ->andWhere('i.status = :status')
            ->andWhere('i.dueDate >= :today')
            ->andWhere('i.dueDate <= :end')
            ->setParameter('status', InvoiceStatusEnum::PENDING)
            ->setParameter('today', $today)
            ->setParameter('end', $end)
            ->groupBy('yearMonth')
            ->addGroupBy('i.currency')
            ->getQuery()
            ->getArrayResult();

        $totalsByMonth = [];
        foreach ($rows as $row) {
            $totalsByMonth[$row['yearMonth']][] = ['currency' => $row['currency'], 'total' => $row['total']];
        }

        return $totalsByMonth;
    }
}
