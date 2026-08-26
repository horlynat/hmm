<?php

namespace App\Controller\Admin;

use App\Entity\Invoice;
use App\Enum\ExpenseCategoryEnum;
use App\Enum\ExpenseStatusEnum;
use App\Enum\InvoiceStatusEnum;
use App\Repository\AuditLogRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ProjectExpenseRepository;
use App\Repository\ProjectHistoryRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Security\Voter\FinanceVoter;
use App\Service\CurrencyConversionService;
use App\Service\FinanceCsvExporter;
use App\Service\ProjectStatisticsService;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Module Finance : vues transversales tous projets confondus (factures,
 * dépenses, chiffre d'affaires) + export CSV.
 *
 * Volontairement 100% lecture — aucune mutation ici. Créer/modifier une
 * facture, marquer payée, approuver une dépense... restent dans les écrans
 * projet existants (AdminInvoiceController, Live Component ExpenseList),
 * déjà fiables : ce module ne fait que consulter, filtrer et exporter, pour
 * ne jamais dupliquer cette logique métier.
 */
#[Route('/admin/finance', name: 'admin_finance_')]
final class AdminFinanceController extends AbstractController
{
    private const LIMIT = 20;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        ProjectStatisticsService $statisticsService,
        InvoiceRepository $invoiceRepository,
        ProjectExpenseRepository $projectExpenseRepository,
    ): Response {
        $this->denyAccessUnlessGranted(FinanceVoter::VIEW);

        return $this->render('admin/finance/index.html.twig', [
            'revenue' => $statisticsService->getRevenueBreakdown(),
            'overdueInvoicesCount' => count($invoiceRepository->findOverdueForReminder()),
            'pendingExpensesCount' => $projectExpenseRepository->count(['status' => ExpenseStatusEnum::PENDING]),
            'totalInvoicesCount' => $invoiceRepository->count([]),
            'totalExpensesCount' => $projectExpenseRepository->count([]),
            'recentInvoices' => $invoiceRepository->findBy([], ['issuedAt' => 'DESC'], 5),
            'recentExpenses' => $projectExpenseRepository->findBy([], ['createdAt' => 'DESC'], 5),
        ]);
    }

    #[Route('/reports', name: 'reports', methods: ['GET'])]
    public function reports(ProjectStatisticsService $statisticsService, CurrencyConversionService $currencyConversionService): Response
    {
        $this->denyAccessUnlessGranted(FinanceVoter::VIEW);

        return $this->render('admin/finance/reports.html.twig', [
            'profitabilityByProject' => $statisticsService->getProfitabilityByProject(),
            'profitabilityByClient' => $statisticsService->getProfitabilityByClient(),
            'revenueByMonth' => $statisticsService->getRevenueByMonth(6),
            'cashFlowForecast' => $statisticsService->getCashFlowForecast(3),
            'exchangeRates' => $currencyConversionService->getRatesSnapshot(),
            'ledgerCurrency' => CurrencyConversionService::PROJECT_LEDGER_CURRENCY,
        ]);
    }

    /**
     * Journal financier : fusion d'AuditLog (actions sur Invoice) et
     * ProjectHistory (actions expense_*) — même mécanique de fusion
     * qu'AdminSecurityAuditController::index(), mais filtrée à la source à
     * ce qui touche vraiment l'argent, pas l'ensemble du back-office.
     */
    #[Route('/journal', name: 'journal', methods: ['GET'])]
    public function journal(AuditLogRepository $auditLogRepository, ProjectHistoryRepository $projectHistoryRepository): Response
    {
        $this->denyAccessUnlessGranted(FinanceVoter::VIEW);

        $entries = [];

        foreach ($auditLogRepository->findRecent(200) as $auditLog) {
            if (Invoice::class !== $auditLog->getEntityClass()) {
                continue;
            }

            $entries[] = [
                'date' => $auditLog->getCreatedAt(),
                'label' => sprintf('Facture « %s »', $auditLog->getEntityLabel()),
                'actionLabel' => $this->journalActionLabel($auditLog->getAction()),
                'user' => $auditLog->getUser(),
            ];
        }

        foreach ($projectHistoryRepository->findRecent(200) as $history) {
            if (!str_starts_with($history->getAction(), 'expense_')) {
                continue;
            }

            $entries[] = [
                'date' => $history->getCreatedAt(),
                // Copie figée du titre (ProjectHistory::$projectTitle) : reste correct même
                // si le projet a depuis été supprimé (project_id passe à NULL, pas de cascade).
                'label' => sprintf('Projet « %s »', $history->getProjectTitle()),
                'actionLabel' => $this->journalActionLabel($history->getAction()),
                'user' => $history->getUser(),
            ];
        }

        usort($entries, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        return $this->render('admin/finance/journal.html.twig', [
            'entries' => array_slice($entries, 0, 100),
        ]);
    }

    private function journalActionLabel(string $action): string
    {
        return match ($action) {
            'created' => 'Création',
            'updated' => 'Modification',
            'deleted' => 'Suppression',
            'marked_paid' => 'Marquée payée',
            'marked_unpaid' => 'Remise en attente',
            'expense_submitted' => 'Dépense soumise',
            'expense_approved' => 'Dépense approuvée',
            'expense_rejected' => 'Dépense refusée',
            'expense_updated' => 'Dépense modifiée',
            'expense_removed' => 'Dépense retirée',
            default => ucfirst($action),
        };
    }

    #[Route('/invoices', name: 'invoices', methods: ['GET'])]
    public function invoices(
        Request $request,
        InvoiceRepository $invoiceRepository,
        ProjectRepository $projectRepository,
        UserRepository $userRepository,
    ): Response {
        $this->denyAccessUnlessGranted(FinanceVoter::VIEW);

        [$sort, $direction] = $this->parseSort($request, ['issuedAt', 'dueDate', 'amount', 'status', 'number', 'project', 'client'], 'issuedAt');
        $page = max(1, (int) $request->query->get('page', 1));

        // project/client trient sur les alias déjà joints par createFilteredQueryBuilder() (p.title/c.fullName),
        // pas sur une colonne directe d'Invoice.
        $sortColumn = match ($sort) {
            'project' => 'p.title',
            'client' => 'c.fullName',
            default => 'i.'.$sort,
        };

        $invoiceFilters = $this->parseInvoiceFilters($request);

        $queryBuilder = $invoiceRepository->createFilteredQueryBuilder($invoiceFilters)
            ->orderBy($sortColumn, $direction);

        $paginator = new Paginator($queryBuilder);
        $totalCount = $paginator->count();
        $totalPages = (int) ceil($totalCount / self::LIMIT);
        $invoices = $paginator->getQuery()
            ->setFirstResult(($page - 1) * self::LIMIT)
            ->setMaxResults(self::LIMIT)
            ->getResult();

        // Puces de statut rapides (Toutes / En attente / Payées / En retard) : mêmes
        // filtres que la liste, moins status/overdue eux-mêmes, pour rester cohérentes
        // avec une recherche ou un filtre projet/client déjà actif.
        $chipBaseFilters = $invoiceFilters;
        unset($chipBaseFilters['status'], $chipBaseFilters['overdue']);

        return $this->render('admin/finance/invoices.html.twig', [
            'invoices' => $invoices,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'limit' => self::LIMIT,
            'statusCounts' => [
                'all' => $invoiceRepository->countByFilters($chipBaseFilters),
                'pending' => $invoiceRepository->countByFilters([...$chipBaseFilters, 'status' => InvoiceStatusEnum::PENDING]),
                'paid' => $invoiceRepository->countByFilters([...$chipBaseFilters, 'status' => InvoiceStatusEnum::PAID]),
                'overdue' => $invoiceRepository->countByFilters([...$chipBaseFilters, 'overdue' => true]),
            ],
            'projects' => $projectRepository->findAll(),
            'clients' => $userRepository->findClients(),
            'currencies' => $invoiceRepository->findDistinctCurrencies(),
            'statuses' => InvoiceStatusEnum::cases(),
            'filters' => $this->rawInvoiceFilters($request, $sort, $direction),
        ]);
    }

    #[Route('/invoices/export', name: 'invoices_export', methods: ['GET'])]
    public function invoicesExport(Request $request, InvoiceRepository $invoiceRepository, FinanceCsvExporter $exporter): Response
    {
        $this->denyAccessUnlessGranted(FinanceVoter::EXPORT);

        $queryBuilder = $invoiceRepository->createFilteredQueryBuilder($this->parseInvoiceFilters($request))
            ->orderBy('i.issuedAt', 'DESC');

        $rows = (static function () use ($queryBuilder, $exporter): iterable {
            foreach ($queryBuilder->getQuery()->toIterable() as $invoice) {
                yield $exporter->invoiceRow($invoice);
            }
        })();

        return $exporter->stream('factures.csv', $exporter->invoiceHeaders(), $rows);
    }

    #[Route('/expenses', name: 'expenses', methods: ['GET'])]
    public function expenses(
        Request $request,
        ProjectExpenseRepository $projectExpenseRepository,
        ProjectRepository $projectRepository,
    ): Response {
        $this->denyAccessUnlessGranted(FinanceVoter::VIEW);

        [$sort, $direction] = $this->parseSort($request, ['createdAt', 'spentAt', 'amount', 'status', 'category'], 'createdAt');
        $page = max(1, (int) $request->query->get('page', 1));

        $queryBuilder = $projectExpenseRepository->createFilteredQueryBuilder($this->parseExpenseFilters($request))
            ->orderBy('e.'.$sort, $direction);

        $paginator = new Paginator($queryBuilder);
        $totalPages = (int) ceil($paginator->count() / self::LIMIT);
        $expenses = $paginator->getQuery()
            ->setFirstResult(($page - 1) * self::LIMIT)
            ->setMaxResults(self::LIMIT)
            ->getResult();

        return $this->render('admin/finance/expenses.html.twig', [
            'expenses' => $expenses,
            'page' => $page,
            'totalPages' => $totalPages,
            'projects' => $projectRepository->findAll(),
            'statuses' => ExpenseStatusEnum::cases(),
            'categories' => ExpenseCategoryEnum::cases(),
            'filters' => $this->rawExpenseFilters($request, $sort, $direction),
        ]);
    }

    #[Route('/expenses/export', name: 'expenses_export', methods: ['GET'])]
    public function expensesExport(Request $request, ProjectExpenseRepository $projectExpenseRepository, FinanceCsvExporter $exporter): Response
    {
        $this->denyAccessUnlessGranted(FinanceVoter::EXPORT);

        $queryBuilder = $projectExpenseRepository->createFilteredQueryBuilder($this->parseExpenseFilters($request))
            ->orderBy('e.createdAt', 'DESC');

        $rows = (static function () use ($queryBuilder, $exporter): iterable {
            foreach ($queryBuilder->getQuery()->toIterable() as $expense) {
                yield $exporter->expenseRow($expense);
            }
        })();

        return $exporter->stream('depenses.csv', $exporter->expenseHeaders(), $rows);
    }

    /**
     * @param string[] $allowed
     *
     * @return array{0: string, 1: string}
     */
    private function parseSort(Request $request, array $allowed, string $default): array
    {
        $sort = (string) $request->query->get('sort', $default);
        if (!in_array($sort, $allowed, true)) {
            $sort = $default;
        }

        $direction = 'ASC' === strtoupper((string) $request->query->get('direction', 'desc')) ? 'ASC' : 'DESC';

        return [$sort, $direction];
    }

    /** Borne de fin inclusive sur toute la journée saisie. Retourne null sur une date malformée plutôt que de planter la requête. */
    private function parseDate(?string $raw, bool $endOfDay = false): ?\DateTimeImmutable
    {
        if (null === $raw || '' === $raw) {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }

        return $endOfDay ? $date->modify('+1 day -1 second') : $date;
    }

    /** @return array<string, mixed> */
    private function parseInvoiceFilters(Request $request): array
    {
        return [
            'status' => $request->query->get('status') ?: null,
            'overdue' => $request->query->get('overdue') ?: null,
            'project' => $request->query->get('project') ?: null,
            'client' => $request->query->get('client') ?: null,
            'currency' => $request->query->get('currency') ?: null,
            'search' => $request->query->get('search') ?: null,
            'min' => $request->query->get('min') ?: null,
            'max' => $request->query->get('max') ?: null,
            'start' => $this->parseDate($request->query->get('start')),
            'end' => $this->parseDate($request->query->get('end'), endOfDay: true),
        ];
    }

    /** @return array<string, mixed> */
    private function parseExpenseFilters(Request $request): array
    {
        return [
            'status' => $request->query->get('status') ?: null,
            'category' => $request->query->get('category') ?: null,
            'project' => $request->query->get('project') ?: null,
            'min' => $request->query->get('min') ?: null,
            'max' => $request->query->get('max') ?: null,
            'start' => $this->parseDate($request->query->get('start')),
            'end' => $this->parseDate($request->query->get('end'), endOfDay: true),
        ];
    }

    /** @return array<string, mixed> */
    private function rawInvoiceFilters(Request $request, string $sort, string $direction): array
    {
        return [
            'status' => $request->query->get('status'),
            'overdue' => $request->query->get('overdue'),
            'project' => $request->query->get('project'),
            'client' => $request->query->get('client'),
            'currency' => $request->query->get('currency'),
            'search' => $request->query->get('search'),
            'min' => $request->query->get('min'),
            'max' => $request->query->get('max'),
            'start' => $request->query->get('start'),
            'end' => $request->query->get('end'),
            'sort' => $sort,
            'direction' => strtolower($direction),
        ];
    }

    /** @return array<string, mixed> */
    private function rawExpenseFilters(Request $request, string $sort, string $direction): array
    {
        return [
            'status' => $request->query->get('status'),
            'category' => $request->query->get('category'),
            'project' => $request->query->get('project'),
            'min' => $request->query->get('min'),
            'max' => $request->query->get('max'),
            'start' => $request->query->get('start'),
            'end' => $request->query->get('end'),
            'sort' => $sort,
            'direction' => strtolower($direction),
        ];
    }
}
