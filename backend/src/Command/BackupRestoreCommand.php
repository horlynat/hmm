<?php

namespace App\Command;

use App\Service\DatabaseBackupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Restaure la base depuis une sauvegarde locale (var/backups), en CLI.
 *
 * Jusqu'ici, DatabaseBackupService::restore() n'était atteignable que via
 * AdminBackupController::restore() (web, SUPER_ADMIN) — un problème pour le
 * scénario même où une restauration est le plus probable : reconstruction
 * après perte du VPS (cf. docs/incident-data-loss.md §2), où le site n'a pas
 * encore de compte admin utilisable, voire pas encore de schéma cohérent
 * pour se connecter du tout. Volontairement en CLI, comme app:admin:recover.
 */
#[AsCommand(name: 'app:backup:restore', description: 'Restaure la base de données depuis une sauvegarde locale (ÉCRASE la base courante).')]
class BackupRestoreCommand extends Command
{
    public function __construct(private readonly DatabaseBackupService $backupService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('filename', InputArgument::REQUIRED, 'Nom du fichier de sauvegarde (ex: backup_20260826_030000.sql), déjà présent dans var/backups.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ignore la confirmation interactive (scripts non-interactifs).')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filename = (string) $input->getArgument('filename');

        $io->warning(sprintf('Ceci va ÉCRASER intégralement la base de données actuelle avec le contenu de "%s".', $filename));
        if (!$input->getOption('force') && !$io->confirm('Confirmer la restauration ?', false)) {
            $io->comment('Annulé.');

            return Command::SUCCESS;
        }

        try {
            $this->backupService->restore($filename);
        } catch (\Throwable $e) {
            $io->error(sprintf('Échec de la restauration : %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(sprintf('Base de données restaurée depuis "%s".', $filename));

        return Command::SUCCESS;
    }
}
