<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\InvoiceStatusEnum;
use App\Enum\ProjectStatusEnum;
use App\Repository\InvoiceRepository;
use App\Repository\ProjectExpenseRepository;
use App\Repository\QuoteRequestRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service pour calculer les statistiques liées aux projets.
 * - Statistiques globales par utilisateur
 * - Statistiques détaillées par projet
 * - Données pour les graphiques du dashboard
 * - Répartition du chiffre d'affaires (voir getRevenueBreakdown())
 * - Rentabilité et prévisionnel (voir getProfitabilityByProject() et suivantes).
 */
class ProjectStatisticsService
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly QuoteRequestRepository $quoteRequestRepository,
        private readonly CurrencyConversionService $currencyConversionService,
        private readonly QuoteBudgetParser $quoteBudgetParser,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly ProjectExpenseRepository $projectExpenseRepository,
    ) {
        $this->entityManager = $entityManager;
    }

    /**
     * 📊 Statistiques globales pour un utilisateur.
     *
     * @return array{totalBudget: float, totalSpent: float, totalProjects: int, overBudgetCount: int, lowBudgetCount: int, remainingBudget: float}
     */
    public function getUserProjectStatistics(User $user): array
    {
        $query = $this->entityManager->createQuery(
            'SELECT
                SUM(p.budget) AS totalBudget,
                SUM(p.spent) AS totalSpent,
                COUNT(p.id) AS totalProjects,
                SUM(CASE WHEN p.spent > p.budget THEN 1 ELSE 0 END) AS overBudgetCount,
                SUM(CASE WHEN p.budget > 0 AND (p.budget - p.spent) / p.budget < 0.1 THEN 1 ELSE 0 END) AS lowBudgetCount
             FROM App\Entity\Project p
             LEFT JOIN p.collaborators c
             WHERE p.owner = :user OR c.id = :user'
        )->setParameter('user', $user);

        // ✅ getSingleResult car plusieurs colonnes
        $result = $query->getSingleResult();

        $totalBudget = (float) ($result['totalBudget'] ?? 0);
        $totalSpent = (float) ($result['totalSpent'] ?? 0);

        return [
            'totalBudget' => $totalBudget,
            'totalSpent' => $totalSpent,
            'totalProjects' => (int) ($result['totalProjects'] ?? 0),
            'overBudgetCount' => (int) ($result['overBudgetCount'] ?? 0),
            'lowBudgetCount' => (int) ($result['lowBudgetCount'] ?? 0),
            'remainingBudget' => $totalBudget - $totalSpent,
        ];
    }

    /**
     * 📊 Statistiques détaillées pour un projet.
     *
     * @return array{totalBudget: float, totalSpent: float, remainingBudget: float, percentageUsed: float, expenseCount: int, historyCount: int, collaboratorCount: int}
     */
    public function getProjectStatistics(Project $project): array
    {
        $expenses = $project->getExpenses();
        $totalSpent = 0.0;

        foreach ($expenses as $expense) {
            $totalSpent += (float) $expense->getAmount();
        }

        $budget = (float) $project->getBudget();
        $remainingBudget = $budget - $totalSpent;

        // ✅ Calcul correct du pourcentage utilisé
        $percentageUsed = $budget > 0
            ? min(100, round(($totalSpent / $budget) * 100, 2))
            : 0;

        return [
            'totalBudget' => $budget,
            'totalSpent' => $totalSpent,
            'remainingBudget' => $remainingBudget,
            'percentageUsed' => $percentageUsed,
            'expenseCount' => count($expenses),
            'historyCount' => count($project->getHistories()),
            'collaboratorCount' => count($project->getCollaborators()),
        ];
    }

    /**
     * 📊 Données pour les graphiques du dashboard.
     *
     * @return array{projectsByStatus: array<string, int>, budgetByStatus: array<string, float>, expensesByMonth: array<string, float>}
     */
    public function getChartData(): array
    {
        $entityManager = $this->entityManager;

        // Projets par statut
        $projectsByStatus = [];
        foreach (ProjectStatusEnum::cases() as $status) {
            $count = $entityManager->getRepository(Project::class)->count(['status' => $status]);
            $projectsByStatus[$status->getLabel()] = $count;
        }

        // Budget par statut
        $budgetByStatus = [];
        foreach (ProjectStatusEnum::cases() as $status) {
            $result = $entityManager->createQuery(
                'SELECT SUM(p.budget) AS total FROM App\Entity\Project p WHERE p.status = :status'
            )->setParameter('status', $status)
             ->getSingleScalarResult();

            $budgetByStatus[$status->getLabel()] = (float) ($result ?? 0);
        }

        // Dépenses par mois (6 derniers mois)
        $expensesByMonth = [];
        for ($i = 5; $i >= 0; --$i) {
            $month = (new \DateTime())->modify("-$i months")->format('Y-m');
            $result = $entityManager->createQuery(
                'SELECT SUM(e.amount) AS total FROM App\Entity\ProjectExpense e
                 WHERE e.createdAt BETWEEN :start AND :end'
            )
            ->setParameter('start', new \DateTimeImmutable($month.'-01'))
            ->setParameter('end', (new \DateTimeImmutable($month.'-01'))->modify('+1 month -1 day'))
            ->getSingleScalarResult();

            $expensesByMonth[$month] = (float) ($result ?? 0);
        }

        return [
            'projectsByStatus' => $projectsByStatus,
            'budgetByStatus' => $budgetByStatus,
            'expensesByMonth' => $expensesByMonth,
        ];
    }

    /**
     * Répartition du chiffre d'affaires en trois masses strictement
     * distinctes, converties dans la devise de référence des livres de
     * compte (CurrencyConversionService::PROJECT_LEDGER_CURRENCY).
     *
     * INVARIANT — ne jamais additionner ces trois valeurs entre elles :
     * - realizedRevenue  : argent réellement encaissé (factures payées uniquement).
     *   C'est la SEULE valeur qui a le droit d'être appelée "chiffre d'affaires".
     * - invoicedPending  : argent facturé mais pas encore payé — engagé, pas encaissé.
     * - pipelineEstimate : estimation du pipeline commercial (devis pas encore
     *   décidés ou tout juste acceptés) — ni facturé, ni dû. Reste une simple
     *   estimation : budget en texte libre, parfois non interprétable
     *   (voir pipelineUnparseableCount), jamais garantie.
     *
     * Un devis n'entre dans realizedRevenue ou invoicedPending qu'après avoir
     * été transformé en Project (AdminQuoteRequestController::convert(),
     * réservé aux devis ACCEPTED) *et* facturé — jamais avant, et jamais par
     * son seul budget déclaratif.
     *
     * @return array{
     *     realizedRevenue: string,
     *     realizedRevenueCount: int,
     *     realizedRevenueUnconvertedCount: int,
     *     invoicedPending: string,
     *     invoicedPendingCount: int,
     *     pipelineEstimate: string,
     *     pipelineQuoteCount: int,
     *     pipelineUnparseableCount: int,
     *     ledgerCurrency: string,
     * }
     */
    public function getRevenueBreakdown(): array
    {
        $ledgerCurrency = CurrencyConversionService::PROJECT_LEDGER_CURRENCY;

        $paidInvoices = $this->entityManager->getRepository(Invoice::class)
            ->findBy(['status' => InvoiceStatusEnum::PAID]);
        [$realizedRevenue, $realizedRevenueUnconvertedCount] = $this->sumInvoices($paidInvoices, $ledgerCurrency);

        $pendingInvoices = $this->entityManager->getRepository(Invoice::class)
            ->findBy(['status' => [InvoiceStatusEnum::PENDING, InvoiceStatusEnum::REVISION_REQUESTED]]);
        [$invoicedPending] = $this->sumInvoices($pendingInvoices, $ledgerCurrency);

        $activeQuotes = $this->quoteRequestRepository->findActive();
        $pipelineEstimate = '0.00';
        $pipelineUnparseableCount = 0;
        foreach ($activeQuotes as $quote) {
            $parsed = $this->quoteBudgetParser->parse($quote->getBudget());
            if (null === $parsed) {
                ++$pipelineUnparseableCount;
                continue;
            }

            $converted = $this->currencyConversionService->convert($parsed, $quote->getCurrency() ?? $ledgerCurrency, $ledgerCurrency);
            if (null === $converted) {
                ++$pipelineUnparseableCount;
                continue;
            }

            $pipelineEstimate = bcadd($pipelineEstimate, $converted, 2);
        }

        return [
            'realizedRevenue' => $realizedRevenue,
            'realizedRevenueCount' => count($paidInvoices),
            'realizedRevenueUnconvertedCount' => $realizedRevenueUnconvertedCount,
            'invoicedPending' => $invoicedPending,
            'invoicedPendingCount' => count($pendingInvoices),
            'pipelineEstimate' => $pipelineEstimate,
            'pipelineQuoteCount' => count($activeQuotes),
            'pipelineUnparseableCount' => $pipelineUnparseableCount,
            'ledgerCurrency' => $ledgerCurrency,
        ];
    }

    /**
     * @param Invoice[] $invoices
     *
     * @return array{0: string, 1: int} montant total converti, nombre de factures exclues faute de taux de change disponible
     */
    private function sumInvoices(array $invoices, string $targetCurrency): array
    {
        $total = '0.00';
        $unconvertedCount = 0;

        foreach ($invoices as $invoice) {
            $converted = $this->currencyConversionService->convert($invoice->getAmount(), $invoice->getCurrency(), $targetCurrency);
            if (null === $converted) {
                ++$unconvertedCount;
                continue;
            }

            $total = bcadd($total, $converted, 2);
        }

        return [$total, $unconvertedCount];
    }

    /**
     * Marge réalisée par projet : première fois que ce code croise le CA
     * réellement encaissé auprès du client (factures payées) et les coûts
     * internes (dépenses approuvées) — jusqu'ici, "budget" ne comparait le
     * budget alloué qu'à la dépense interne, jamais à ce qui a été facturé.
     *
     * Ne liste que les projets ayant au moins une facture payée ou une
     * dépense approuvée ; un projet sans aucune activité financière
     * n'apparaît pas (rien à montrer). Trié par marge décroissante.
     *
     * @return array<int, array{project: Project, client: ?User, revenue: string, cost: string, margin: string, marginPercent: ?float}>
     */
    public function getProfitabilityByProject(): array
    {
        $ledgerCurrency = CurrencyConversionService::PROJECT_LEDGER_CURRENCY;

        $invoiceTotalsByProject = $this->invoiceRepository->getPaidTotalsByProjectAndCurrency();
        $expenseTotalsByProject = $this->projectExpenseRepository->getApprovedTotalsByProject();

        $projectIds = array_unique(array_merge(array_keys($invoiceTotalsByProject), array_keys($expenseTotalsByProject)));
        if ([] === $projectIds) {
            return [];
        }

        $projects = $this->entityManager->getRepository(Project::class)->findBy(['id' => $projectIds]);

        $rows = [];
        foreach ($projects as $project) {
            [$revenue] = $this->sumCurrencyGroups($invoiceTotalsByProject[$project->getId()] ?? [], $ledgerCurrency);
            $cost = $expenseTotalsByProject[$project->getId()] ?? '0.00';
            $margin = bcsub($revenue, $cost, 2);

            $rows[] = [
                'project' => $project,
                'client' => $project->getClient(),
                'revenue' => $revenue,
                'cost' => $cost,
                'margin' => $margin,
                'marginPercent' => bccomp($revenue, '0.00', 2) > 0
                    ? round((float) bcdiv($margin, $revenue, 4) * 100, 1)
                    : null,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => bccomp($b['margin'], $a['margin'], 2));

        return $rows;
    }

    /**
     * Même marge réalisée que getProfitabilityByProject(), agrégée par
     * client (Project::client) plutôt que par projet. Les projets sans
     * client lié (jamais rattaché à un compte — voir l'audit du cycle de
     * vie des devis) sont regroupés sous une entrée "client" nulle.
     *
     * @return array<int, array{client: ?User, revenue: string, cost: string, margin: string}>
     */
    public function getProfitabilityByClient(): array
    {
        $byClient = [];
        foreach ($this->getProfitabilityByProject() as $row) {
            $clientId = $row['client']?->getId() ?? 0;
            $byClient[$clientId] ??= ['client' => $row['client'], 'revenue' => '0.00', 'cost' => '0.00'];
            $byClient[$clientId]['revenue'] = bcadd($byClient[$clientId]['revenue'], $row['revenue'], 2);
            $byClient[$clientId]['cost'] = bcadd($byClient[$clientId]['cost'], $row['cost'], 2);
        }

        $rows = array_values(array_map(static function (array $entry): array {
            $entry['margin'] = bcsub($entry['revenue'], $entry['cost'], 2);

            return $entry;
        }, $byClient));

        usort($rows, static fn (array $a, array $b): int => bccomp($b['margin'], $a['margin'], 2));

        return $rows;
    }

    /**
     * CA encaissé mensuel glissant sur les $months derniers mois — même
     * principe qu'expensesByMonth (getChartData()), côté revenu plutôt que
     * dépense. Les mois sans aucun encaissement apparaissent à "0.00", pas
     * absents, pour que l'axe temporel reste continu côté affichage.
     *
     * @return array<string, string> total encaissé (devise de référence) indexé par "Y-m"
     */
    public function getRevenueByMonth(int $months = 6): array
    {
        $ledgerCurrency = CurrencyConversionService::PROJECT_LEDGER_CURRENCY;
        $totalsByMonth = $this->invoiceRepository->getPaidTotalsByMonthAndCurrency($months);

        $result = [];
        for ($i = $months - 1; $i >= 0; --$i) {
            $month = (new \DateTime())->modify("-$i months")->format('Y-m');
            [$total] = $this->sumCurrencyGroups($totalsByMonth[$month] ?? [], $ledgerCurrency);
            $result[$month] = $total;
        }

        return $result;
    }

    /**
     * Prévisionnel de trésorerie simple : factures en attente groupées par
     * mois d'échéance, sur les $months prochains mois. Ce n'est PAS un
     * modèle prédictif — juste ce qui est déjà facturé et dû, agrégé dans le
     * temps. Aucune facturation récurrente n'existe dans l'app pour projeter
     * au-delà de ce qui est déjà émis.
     *
     * @return array<string, string> total attendu (devise de référence) indexé par "Y-m"
     */
    public function getCashFlowForecast(int $months = 3): array
    {
        $ledgerCurrency = CurrencyConversionService::PROJECT_LEDGER_CURRENCY;
        $totalsByMonth = $this->invoiceRepository->getPendingTotalsByDueMonthAndCurrency($months);

        $result = [];
        for ($i = 0; $i < $months; ++$i) {
            $month = (new \DateTime())->modify("+$i months")->format('Y-m');
            [$total] = $this->sumCurrencyGroups($totalsByMonth[$month] ?? [], $ledgerCurrency);
            $result[$month] = $total;
        }

        return $result;
    }

    /**
     * @param array{currency: string, total: string}[] $entries
     *
     * @return array{0: string, 1: int} montant total converti, nombre de groupes exclus faute de taux de change disponible
     */
    private function sumCurrencyGroups(array $entries, string $targetCurrency): array
    {
        $total = '0.00';
        $unconvertedCount = 0;

        foreach ($entries as $entry) {
            $converted = $this->currencyConversionService->convert($entry['total'], $entry['currency'], $targetCurrency);
            if (null === $converted) {
                ++$unconvertedCount;
                continue;
            }

            $total = bcadd($total, $converted, 2);
        }

        return [$total, $unconvertedCount];
    }
}
