<?php

namespace App\Tests\Service;

use App\Entity\Invoice;
use App\Entity\QuoteRequest;
use App\Enum\InvoiceStatusEnum;
use App\Enum\QuoteStatusEnum;
use App\Repository\InvoiceRepository;
use App\Repository\ProjectExpenseRepository;
use App\Repository\QuoteRequestRepository;
use App\Service\CurrencyConversionService;
use App\Service\ProjectStatisticsService;
use App\Service\QuoteBudgetParser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * Couvre l'invariant central de getRevenueBreakdown() : le chiffre d'affaires
 * (factures payées) ne doit jamais inclure le budget des devis, et le
 * pipeline de devis ne doit jamais être compté comme du chiffre d'affaires —
 * même quand un devis a été converti en projet facturé.
 */
final class ProjectStatisticsServiceRevenueTest extends TestCase
{
    private function invoice(string $amount, InvoiceStatusEnum $status, string $currency = 'EUR'): Invoice
    {
        return (new Invoice())->setAmount($amount)->setCurrency($currency)->setStatus($status);
    }

    private function quote(QuoteStatusEnum $status, ?string $budget, string $currency = 'EUR'): QuoteRequest
    {
        return (new QuoteRequest())->setStatus($status)->setBudget($budget)->setCurrency($currency);
    }

    /**
     * @param Invoice[] $paidInvoices
     * @param Invoice[] $pendingInvoices
     */
    private function service(array $paidInvoices, array $pendingInvoices, QuoteRequestRepository $quoteRequestRepository): ProjectStatisticsService
    {
        $invoiceRepository = $this->createStub(EntityRepository::class);
        $invoiceRepository->method('findBy')->willReturnCallback(
            function (array $criteria) use ($paidInvoices, $pendingInvoices) {
                if (InvoiceStatusEnum::PAID === $criteria['status']) {
                    return $paidInvoices;
                }

                return $pendingInvoices;
            },
        );

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($invoiceRepository);

        // Aucun montant de ce test n'est dans une devise étrangère : aucun appel HTTP ne doit avoir lieu.
        $currencyConversion = new CurrencyConversionService(
            new MockHttpClient(function () {
                self::fail('Aucune conversion de devise ne devrait être nécessaire dans ce test (tout est déjà en EUR).');
            }),
            new ArrayAdapter(),
            new NullLogger(),
        );

        // Non utilisés par getRevenueBreakdown() : stubs vides, jamais interrogés dans ces tests.
        return new ProjectStatisticsService(
            $entityManager,
            $quoteRequestRepository,
            $currencyConversion,
            new QuoteBudgetParser(),
            $this->createStub(InvoiceRepository::class),
            $this->createStub(ProjectExpenseRepository::class),
        );
    }

    /** @param QuoteRequest[] $activeQuotes */
    private function quoteRequestRepositoryReturning(array $activeQuotes): QuoteRequestRepository
    {
        $repo = $this->createStub(QuoteRequestRepository::class);
        $repo->method('findActive')->willReturn($activeQuotes);

        return $repo;
    }

    public function testRealizedRevenueOnlyCountsPaidInvoices(): void
    {
        $paid = [$this->invoice('1000.00', InvoiceStatusEnum::PAID), $this->invoice('500.00', InvoiceStatusEnum::PAID)];
        $pending = [$this->invoice('2000.00', InvoiceStatusEnum::PENDING)];

        $service = $this->service($paid, $pending, $this->quoteRequestRepositoryReturning([]));
        $breakdown = $service->getRevenueBreakdown();

        self::assertSame('1500.00', $breakdown['realizedRevenue']);
        self::assertSame(2, $breakdown['realizedRevenueCount']);
    }

    public function testInvoicedPendingNeverBleedsIntoRealizedRevenue(): void
    {
        $paid = [$this->invoice('1000.00', InvoiceStatusEnum::PAID)];
        $pending = [
            $this->invoice('2000.00', InvoiceStatusEnum::PENDING),
            $this->invoice('300.00', InvoiceStatusEnum::REVISION_REQUESTED),
        ];

        $service = $this->service($paid, $pending, $this->quoteRequestRepositoryReturning([]));
        $breakdown = $service->getRevenueBreakdown();

        self::assertSame('1000.00', $breakdown['realizedRevenue'], 'Le CA ne doit contenir que les factures payées.');
        self::assertSame('2300.00', $breakdown['invoicedPending']);
        self::assertSame(2, $breakdown['invoicedPendingCount']);
    }

    /**
     * Le cœur de la demande : un devis actif — même accepté, même avec un
     * budget chiffré — ne doit JAMAIS apparaître dans realizedRevenue ni
     * invoicedPending. Il n'existe que dans pipelineEstimate.
     */
    public function testActiveQuoteBudgetNeverInflatesRevenue(): void
    {
        $paid = [$this->invoice('1000.00', InvoiceStatusEnum::PAID)];
        $activeQuotes = [
            $this->quote(QuoteStatusEnum::ACCEPTED, '50000'),
            $this->quote(QuoteStatusEnum::PENDING, '8000-12000'),
        ];

        $service = $this->service($paid, [], $this->quoteRequestRepositoryReturning($activeQuotes));
        $breakdown = $service->getRevenueBreakdown();

        self::assertSame('1000.00', $breakdown['realizedRevenue'], 'Le budget des devis actifs ne doit jamais gonfler le CA.');
        self::assertSame('0.00', $breakdown['invoicedPending']);
        self::assertSame('58000.00', $breakdown['pipelineEstimate']);
        self::assertSame(2, $breakdown['pipelineQuoteCount']);
    }

    public function testUnparseableQuoteBudgetIsExcludedButCountedSeparately(): void
    {
        $activeQuotes = [
            $this->quote(QuoteStatusEnum::PENDING, 'à discuter'),
            $this->quote(QuoteStatusEnum::PENDING, '3000'),
        ];

        $service = $this->service([], [], $this->quoteRequestRepositoryReturning($activeQuotes));
        $breakdown = $service->getRevenueBreakdown();

        self::assertSame('3000.00', $breakdown['pipelineEstimate']);
        self::assertSame(2, $breakdown['pipelineQuoteCount']);
        self::assertSame(1, $breakdown['pipelineUnparseableCount']);
    }

    public function testEmptyStateReturnsZeroedBreakdownWithoutError(): void
    {
        $service = $this->service([], [], $this->quoteRequestRepositoryReturning([]));
        $breakdown = $service->getRevenueBreakdown();

        self::assertSame('0.00', $breakdown['realizedRevenue']);
        self::assertSame('0.00', $breakdown['invoicedPending']);
        self::assertSame('0.00', $breakdown['pipelineEstimate']);
        self::assertSame('EUR', $breakdown['ledgerCurrency']);
    }
}
