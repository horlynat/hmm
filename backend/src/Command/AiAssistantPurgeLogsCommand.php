<?php

namespace App\Command;

use App\Repository\AiAssistantConversationLogRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Purge les logs de conversation de l'assistant IA de plus de 90 jours —
 * garde-fou RGPD documenté en §4.3 du document d'architecture assistant IA.
 * À planifier en cron (cf. main/infra/README.md), aux côtés de backup.sh.
 */
#[AsCommand(name: 'app:ai-assistant:purge-logs', description: 'Purge les logs de conversation de l\'assistant IA de plus de 90 jours.')]
class AiAssistantPurgeLogsCommand extends Command
{
    private const RETENTION_DAYS = 90;

    public function __construct(private readonly AiAssistantConversationLogRepository $logRepository)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $threshold = new \DateTimeImmutable(sprintf('-%d days', self::RETENTION_DAYS));
        $deleted = $this->logRepository->deleteOlderThan($threshold);

        $io->success(sprintf('%d log(s) de conversation supprimé(s) (antérieur(s) au %s).', $deleted, $threshold->format('d/m/Y')));

        return Command::SUCCESS;
    }
}
