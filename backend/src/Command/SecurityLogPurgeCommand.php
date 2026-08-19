<?php

namespace App\Command;

use App\Entity\FailedLoginAttempt;
use App\Entity\LoginHistory;
use App\Repository\FailedLoginAttemptRepository;
use App\Repository\LoginHistoryRepository;
use App\Service\AuditLogger;
use App\Service\SecurityLogRetentionPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Purge le journal de connexions (LoginHistory + FailedLoginAttempt) au-delà
 * des durées de SecurityLogRetentionPolicy — même rôle que
 * app:ai-assistant:purge-logs pour l'assistant IA, appliqué ici au journal de
 * sécurité qui n'avait jusqu'ici aucun mécanisme d'effacement.
 *
 * Auditée via AuditLogger (avec un utilisateur nul, résolu automatiquement en
 * contexte CLI) : ce qui est supprimé est le journal de sécurité lui-même,
 * sa suppression doit rester traçable même déclenchée automatiquement —
 * mêmes raisons que la purge manuelle (AdminSecurityLogController::purge()).
 *
 * À planifier en cron (cf. main/infra/README.md), aux côtés de backup.sh et
 * app:ai-assistant:purge-logs.
 */
#[AsCommand(name: 'app:security-log:purge', description: 'Purge le journal de connexions (réussites et échecs) au-delà de la durée de rétention.')]
class SecurityLogPurgeCommand extends Command
{
    public function __construct(
        private readonly LoginHistoryRepository $loginHistoryRepository,
        private readonly FailedLoginAttemptRepository $failedLoginAttemptRepository,
        private readonly AuditLogger $auditLogger,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $loginThreshold = new \DateTimeImmutable(sprintf('-%d days', SecurityLogRetentionPolicy::LOGIN_HISTORY_RETENTION_DAYS));
        $deletedLogins = $this->loginHistoryRepository->deleteOlderThan($loginThreshold);
        $io->success(sprintf('%d connexion(s) réussie(s) supprimée(s) (antérieure(s) au %s).', $deletedLogins, $loginThreshold->format('d/m/Y')));
        if ($deletedLogins > 0) {
            $this->auditLogger->log(LoginHistory::class, 0, sprintf('%d ligne(s)', $deletedLogins), 'security_log_purged', sprintf('Purge automatique (cron) : %d connexion(s) réussie(s) supprimée(s) (> %d jours).', $deletedLogins, SecurityLogRetentionPolicy::LOGIN_HISTORY_RETENTION_DAYS));
        }

        $failedThreshold = new \DateTimeImmutable(sprintf('-%d days', SecurityLogRetentionPolicy::FAILED_ATTEMPT_RETENTION_DAYS));
        $deletedFailed = $this->failedLoginAttemptRepository->deleteOlderThan($failedThreshold);
        $io->success(sprintf('%d tentative(s) échouée(s) supprimée(s) (antérieure(s) au %s).', $deletedFailed, $failedThreshold->format('d/m/Y')));
        if ($deletedFailed > 0) {
            $this->auditLogger->log(FailedLoginAttempt::class, 0, sprintf('%d ligne(s)', $deletedFailed), 'security_log_purged', sprintf('Purge automatique (cron) : %d tentative(s) échouée(s) supprimée(s) (> %d jours).', $deletedFailed, SecurityLogRetentionPolicy::FAILED_ATTEMPT_RETENTION_DAYS));
        }

        if ($deletedLogins > 0 || $deletedFailed > 0) {
            $this->entityManager->flush();
        }

        return Command::SUCCESS;
    }
}
