<?php

namespace App\Command;

use App\Service\DatabaseBackupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Permet de planifier les sauvegardes en dehors du back-office (cron système),
 * en s'appuyant sur le même service que AdminBackupController::create().
 */
#[AsCommand(name: 'app:backup:create', description: 'Crée une sauvegarde de la base de données.')]
class BackupCreateCommand extends Command
{
    public function __construct(private readonly DatabaseBackupService $backupService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $filename = $this->backupService->create();
        } catch (\Throwable $e) {
            $io->error(sprintf('Échec de la sauvegarde : %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(sprintf('Sauvegarde créée : %s', $filename));

        return Command::SUCCESS;
    }
}
