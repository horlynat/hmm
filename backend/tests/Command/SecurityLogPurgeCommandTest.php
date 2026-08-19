<?php

namespace App\Tests\Command;

use App\Command\SecurityLogPurgeCommand;
use App\Entity\FailedLoginAttempt;
use App\Entity\LoginHistory;
use App\Repository\FailedLoginAttemptRepository;
use App\Repository\LoginHistoryRepository;
use App\Service\AuditLogger;
use App\Service\SecurityLogRetentionPolicy;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SecurityLogPurgeCommandTest extends TestCase
{
    public function testPurgesBothRepositoriesAndAuditsEachDeletion(): void
    {
        $loginHistoryRepository = $this->createMock(LoginHistoryRepository::class);
        $loginHistoryRepository->expects($this->once())
            ->method('deleteOlderThan')
            ->with($this->callback(function (\DateTimeImmutable $threshold): bool {
                $expected = new \DateTimeImmutable(sprintf('-%d days', SecurityLogRetentionPolicy::LOGIN_HISTORY_RETENTION_DAYS));

                // Tolérance d'une seconde : l'appel réel et l'attendu ne sont pas calculés
                // exactement à la même microseconde.
                return abs($threshold->getTimestamp() - $expected->getTimestamp()) <= 1;
            }))
            ->willReturn(7);

        $failedLoginAttemptRepository = $this->createMock(FailedLoginAttemptRepository::class);
        $failedLoginAttemptRepository->expects($this->once())
            ->method('deleteOlderThan')
            ->with($this->callback(function (\DateTimeImmutable $threshold): bool {
                $expected = new \DateTimeImmutable(sprintf('-%d days', SecurityLogRetentionPolicy::FAILED_ATTEMPT_RETENTION_DAYS));

                return abs($threshold->getTimestamp() - $expected->getTimestamp()) <= 1;
            }))
            ->willReturn(42);

        // La suppression du journal de sécurité doit elle-même rester traçable
        // (cf. docblock de la commande) : une entrée par type supprimé. Les
        // appels sont capturés puis vérifiés après coup (withConsecutive
        // n'existe plus en PHPUnit 13) — plus précis qu'un logicalOr qui
        // accepterait n'importe quel appariement classe/libellé.
        $loggedCalls = [];
        $auditLogger = $this->createMock(AuditLogger::class);
        $auditLogger->expects($this->exactly(2))
            ->method('log')
            ->willReturnCallback(function (string $entityClass, int $entityId, string $entityLabel, string $action) use (&$loggedCalls): void {
                $loggedCalls[] = [$entityClass, $entityId, $entityLabel, $action];
            });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $command = new SecurityLogPurgeCommand($loginHistoryRepository, $failedLoginAttemptRepository, $auditLogger, $entityManager);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($application->find('app:security-log:purge'));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('7 connexion(s) réussie(s) supprimée(s)', $tester->getDisplay());
        self::assertStringContainsString('42 tentative(s) échouée(s) supprimée(s)', $tester->getDisplay());
        self::assertContains([LoginHistory::class, 0, '7 ligne(s)', 'security_log_purged'], $loggedCalls);
        self::assertContains([FailedLoginAttempt::class, 0, '42 ligne(s)', 'security_log_purged'], $loggedCalls);
    }

    public function testReportsZeroAndSkipsAuditWhenNothingToPurge(): void
    {
        $loginHistoryRepository = $this->createStub(LoginHistoryRepository::class);
        $loginHistoryRepository->method('deleteOlderThan')->willReturn(0);

        $failedLoginAttemptRepository = $this->createStub(FailedLoginAttemptRepository::class);
        $failedLoginAttemptRepository->method('deleteOlderThan')->willReturn(0);

        $auditLogger = $this->createMock(AuditLogger::class);
        $auditLogger->expects($this->never())->method('log');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $command = new SecurityLogPurgeCommand($loginHistoryRepository, $failedLoginAttemptRepository, $auditLogger, $entityManager);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($application->find('app:security-log:purge'));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('0 connexion(s) réussie(s) supprimée(s)', $tester->getDisplay());
        self::assertStringContainsString('0 tentative(s) échouée(s) supprimée(s)', $tester->getDisplay());
    }
}
