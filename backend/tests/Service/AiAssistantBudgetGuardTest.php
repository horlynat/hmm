<?php

namespace App\Tests\Service;

use App\Repository\AiAssistantConversationLogRepository;
use App\Service\AdminAlertNotifier;
use App\Service\AiAssistantBudgetGuard;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class AiAssistantBudgetGuardTest extends TestCase
{
    private function createLimiter(string $id): RateLimiterFactory
    {
        return new RateLimiterFactory(
            ['id' => $id, 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 day'],
            new InMemoryStorage(),
        );
    }

    /**
     * AdminAlertNotifier est une classe "final" (comme Notifier, cf.
     * ErrorNotifierTest) : on en construit une vraie instance plutôt que de
     * la mocker. `new Notifier([])` n'a ni policy ni recipient configurés,
     * donc `alert()` échoue systématiquement en interne (LogicException,
     * "Unable to determine which channels...") et journalise via le logger —
     * observer le logger sert donc de proxy fiable pour "alert() a été
     * appelé", sans dépendre d'un vrai transport email/push.
     */
    private function createAdminAlertNotifier(LoggerInterface $logger): AdminAlertNotifier
    {
        return new AdminAlertNotifier(new Notifier([]), $logger);
    }

    private function createGuard(
        float $spent,
        float $monthlyBudgetUsd,
        float $alertThresholdUsd,
        LoggerInterface $logger,
    ): AiAssistantBudgetGuard {
        $repository = $this->createStub(AiAssistantConversationLogRepository::class);
        $repository->method('sumCostThisMonth')->willReturn($spent);

        return new AiAssistantBudgetGuard(
            $repository,
            $this->createAdminAlertNotifier($logger),
            $this->createLimiter('exceeded'),
            $this->createLimiter('warning'),
            $monthlyBudgetUsd,
            $alertThresholdUsd,
        );
    }

    public function testUnderBothThresholdsNeitherBlocksNorAlerts(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $guard = $this->createGuard(spent: 10.0, monthlyBudgetUsd: 100.0, alertThresholdUsd: 60.0, logger: $logger);

        $this->assertFalse($guard->isBudgetExceeded());
    }

    public function testAboveAlertThresholdWarnsButDoesNotBlock(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $guard = $this->createGuard(spent: 65.0, monthlyBudgetUsd: 100.0, alertThresholdUsd: 60.0, logger: $logger);

        $this->assertFalse($guard->isBudgetExceeded());
    }

    public function testAboveMonthlyBudgetBlocksAndAlerts(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $guard = $this->createGuard(spent: 100.0, monthlyBudgetUsd: 100.0, alertThresholdUsd: 60.0, logger: $logger);

        $this->assertTrue($guard->isBudgetExceeded());
    }

    public function testWarningAlertIsNotRepeatedWithinTheSameDay(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $guard = $this->createGuard(spent: 65.0, monthlyBudgetUsd: 100.0, alertThresholdUsd: 60.0, logger: $logger);

        $guard->isBudgetExceeded();
        $guard->isBudgetExceeded();
    }

    public function testAlertThresholdAtOrAboveBudgetIsIgnored(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        // Seuil d'alerte mal configuré (>= plafond) : jamais déclenché, seul
        // le dépassement du plafond lui-même compte.
        $guard = $this->createGuard(spent: 90.0, monthlyBudgetUsd: 100.0, alertThresholdUsd: 100.0, logger: $logger);

        $this->assertFalse($guard->isBudgetExceeded());
    }

    public function testZeroMonthlyBudgetDisablesTrackingEntirely(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $guard = $this->createGuard(spent: 1000.0, monthlyBudgetUsd: 0.0, alertThresholdUsd: 60.0, logger: $logger);

        $this->assertFalse($guard->isBudgetExceeded());
    }
}
