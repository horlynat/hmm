<?php

namespace App\Tests\Service;

use App\Entity\Project;
use App\Entity\User;
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
 * Couvre getProfitabilityByProject()/getProfitabilityByClient() (marge =
 * factures payées − dépenses approuvées, jamais calculée ailleurs dans le
 * code), getRevenueByMonth() et getCashFlowForecast().
 */
final class ProjectStatisticsServiceProfitabilityTest extends TestCase
{
    private function project(int $id, string $title, ?User $client = null): Project
    {
        $project = (new Project())->setTitle($title);
        if (null !== $client) {
            $project->setClient($client);
        }

        (new \ReflectionProperty(Project::class, 'id'))->setValue($project, $id);

        return $project;
    }

    private function user(string $email, ?string $fullName = null, ?int $id = null): User
    {
        $user = (new User())->setEmail($email)->setFullName($fullName);
        if (null !== $id) {
            (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);
        }

        return $user;
    }

    /**
     * @param array<int, array{currency: string, total: string}[]>    $paidTotalsByProject
     * @param array<int, string>                                      $approvedTotalsByProject
     * @param Project[]                                               $projects
     * @param array<string, array{currency: string, total: string}[]> $paidTotalsByMonth
     * @param array<string, array{currency: string, total: string}[]> $pendingTotalsByDueMonth
     */
    private function service(
        array $paidTotalsByProject,
        array $approvedTotalsByProject,
        array $projects,
        array $paidTotalsByMonth = [],
        array $pendingTotalsByDueMonth = [],
    ): ProjectStatisticsService {
        $invoiceRepository = $this->createStub(InvoiceRepository::class);
        $invoiceRepository->method('getPaidTotalsByProjectAndCurrency')->willReturn($paidTotalsByProject);
        $invoiceRepository->method('getPaidTotalsByMonthAndCurrency')->willReturn($paidTotalsByMonth);
        $invoiceRepository->method('getPendingTotalsByDueMonthAndCurrency')->willReturn($pendingTotalsByDueMonth);

        $expenseRepository = $this->createStub(ProjectExpenseRepository::class);
        $expenseRepository->method('getApprovedTotalsByProject')->willReturn($approvedTotalsByProject);

        $projectRepository = $this->createStub(EntityRepository::class);
        $projectRepository->method('findBy')->willReturn($projects);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($projectRepository);

        // Tous les montants de ces tests sont déjà dans la devise de référence (EUR) : aucun appel HTTP attendu.
        $currencyConversion = new CurrencyConversionService(
            new MockHttpClient(function () {
                self::fail('Aucune conversion de devise ne devrait être nécessaire dans ce test (tout est déjà en EUR).');
            }),
            new ArrayAdapter(),
            new NullLogger(),
        );

        return new ProjectStatisticsService(
            $entityManager,
            $this->createStub(QuoteRequestRepository::class),
            $currencyConversion,
            new QuoteBudgetParser(),
            $invoiceRepository,
            $expenseRepository,
        );
    }

    public function testMarginIsRevenueMinusApprovedExpenses(): void
    {
        $project = $this->project(1, 'Refonte site');
        $service = $this->service(
            paidTotalsByProject: [1 => [['currency' => 'EUR', 'total' => '5000.00']]],
            approvedTotalsByProject: [1 => '2000.00'],
            projects: [$project],
        );

        $rows = $service->getProfitabilityByProject();

        self::assertCount(1, $rows);
        self::assertSame('5000.00', $rows[0]['revenue']);
        self::assertSame('2000.00', $rows[0]['cost']);
        self::assertSame('3000.00', $rows[0]['margin']);
        self::assertSame(60.0, $rows[0]['marginPercent']);
    }

    public function testProjectWithOnlyExpensesHasNegativeMarginWithoutError(): void
    {
        $project = $this->project(2, 'Maintenance');
        $service = $this->service(
            paidTotalsByProject: [],
            approvedTotalsByProject: [2 => '800.00'],
            projects: [$project],
        );

        $rows = $service->getProfitabilityByProject();

        self::assertSame('0.00', $rows[0]['revenue']);
        self::assertSame('800.00', $rows[0]['cost']);
        self::assertSame('-800.00', $rows[0]['margin']);
        self::assertNull($rows[0]['marginPercent'], 'Pas de revenu → pas de pourcentage de marge calculable.');
    }

    public function testProjectsWithNoFinancialActivityAtAllAreAbsent(): void
    {
        $service = $this->service(paidTotalsByProject: [], approvedTotalsByProject: [], projects: []);

        self::assertSame([], $service->getProfitabilityByProject());
    }

    public function testRowsAreSortedByDescendingMargin(): void
    {
        $low = $this->project(1, 'Faible marge');
        $high = $this->project(2, 'Forte marge');
        $service = $this->service(
            paidTotalsByProject: [
                1 => [['currency' => 'EUR', 'total' => '1000.00']],
                2 => [['currency' => 'EUR', 'total' => '5000.00']],
            ],
            approvedTotalsByProject: [1 => '900.00', 2 => '500.00'],
            projects: [$low, $high],
        );

        $rows = $service->getProfitabilityByProject();

        self::assertSame('Forte marge', $rows[0]['project']->getTitle());
        self::assertSame('Faible marge', $rows[1]['project']->getTitle());
    }

    public function testProfitabilityByClientAggregatesAcrossProjects(): void
    {
        $client = $this->user('client@example.com', 'Client Un', id: 10);
        $projectA = $this->project(1, 'Projet A', $client);
        $projectB = $this->project(2, 'Projet B', $client);

        $service = $this->service(
            paidTotalsByProject: [
                1 => [['currency' => 'EUR', 'total' => '3000.00']],
                2 => [['currency' => 'EUR', 'total' => '2000.00']],
            ],
            approvedTotalsByProject: [1 => '1000.00', 2 => '500.00'],
            projects: [$projectA, $projectB],
        );

        $rows = $service->getProfitabilityByClient();

        self::assertCount(1, $rows);
        self::assertSame('5000.00', $rows[0]['revenue']);
        self::assertSame('1500.00', $rows[0]['cost']);
        self::assertSame('3500.00', $rows[0]['margin']);
    }

    public function testProfitabilityByClientGroupsClientlessProjectsSeparately(): void
    {
        $withClient = $this->project(1, 'Avec client', $this->user('client@example.com', id: 11));
        $withoutClient = $this->project(2, 'Sans client');

        $service = $this->service(
            paidTotalsByProject: [
                1 => [['currency' => 'EUR', 'total' => '1000.00']],
                2 => [['currency' => 'EUR', 'total' => '2000.00']],
            ],
            approvedTotalsByProject: [],
            projects: [$withClient, $withoutClient],
        );

        $rows = $service->getProfitabilityByClient();

        self::assertCount(2, $rows);
        $nullClientRow = array_values(array_filter($rows, static fn (array $r) => null === $r['client']));
        self::assertCount(1, $nullClientRow);
        self::assertSame('2000.00', $nullClientRow[0]['revenue']);
    }

    public function testRevenueByMonthReturnsContinuousBucketsIncludingZeroMonths(): void
    {
        $service = $this->service(
            paidTotalsByProject: [],
            approvedTotalsByProject: [],
            projects: [],
            paidTotalsByMonth: [
                (new \DateTime())->format('Y-m') => [['currency' => 'EUR', 'total' => '1500.00']],
            ],
        );

        $result = $service->getRevenueByMonth(3);

        self::assertCount(3, $result, 'Doit retourner exactement 3 mois, même ceux à 0.');
        self::assertSame('1500.00', $result[(new \DateTime())->format('Y-m')]);
    }

    public function testCashFlowForecastReturnsContinuousFutureBuckets(): void
    {
        $nextMonth = (new \DateTime('+1 month'))->format('Y-m');
        $service = $this->service(
            paidTotalsByProject: [],
            approvedTotalsByProject: [],
            projects: [],
            pendingTotalsByDueMonth: [
                $nextMonth => [['currency' => 'EUR', 'total' => '2250.00']],
            ],
        );

        $result = $service->getCashFlowForecast(3);

        self::assertCount(3, $result);
        self::assertSame('2250.00', $result[$nextMonth]);
    }
}
