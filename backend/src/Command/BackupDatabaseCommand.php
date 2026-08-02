<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Sauvegarde la base de données (mysqldump compressé, hors ligne de commande
 * pour ne jamais exposer le mot de passe via `ps aux`) et purge les
 * sauvegardes les plus anciennes au-delà de la rétention demandée.
 *
 * Volontairement écrite en commande Symfony plutôt qu'en script shell : les
 * paramètres de connexion sont lus directement depuis la Connection Doctrine
 * déjà configurée par l'app (DATABASE_URL), sans avoir à reparser cette URL
 * ni à dupliquer sa logique de résolution.
 *
 * Destinée à tourner en cron (ex: quotidien). Ne couvre que le stockage
 * local (var/backups) : sur un serveur de prod, ces fichiers doivent en plus
 * être répliqués hors du serveur applicatif (rsync/objet distant) pour
 * survivre à une perte du serveur lui-même — hors périmètre de cette
 * commande, qui pose la première brique.
 */
#[AsCommand(name: 'app:backup:database', description: 'Sauvegarde la base de données (dump compressé) et purge les sauvegardes les plus anciennes.')]
class BackupDatabaseCommand extends Command
{
    private const DEFAULT_KEEP = 14;

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'Dossier de destination des sauvegardes.', $this->projectDir.'/var/backups')
            ->addOption('keep', null, InputOption::VALUE_REQUIRED, 'Nombre de sauvegardes à conserver (les plus anciennes au-delà sont supprimées).', (string) self::DEFAULT_KEEP)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();

        $outputDir = rtrim((string) $input->getOption('output-dir'), '/');
        $keep = max(1, (int) $input->getOption('keep'));

        $filesystem->mkdir($outputDir);

        $params = $this->connection->getParams();
        $host = (string) ($params['host'] ?? '127.0.0.1');
        $port = (string) ($params['port'] ?? 3306);
        $user = (string) ($params['user'] ?? '');
        $password = (string) ($params['password'] ?? '');
        $dbName = (string) ($params['dbname'] ?? '');

        if ('' === $dbName) {
            $io->error("Impossible de déterminer le nom de la base (paramètre \"dbname\" absent de la connexion Doctrine).");

            return Command::FAILURE;
        }

        // --defaults-extra-file plutôt que --password=... en argument : évite
        // d'exposer le mot de passe via `ps aux` le temps de l'exécution.
        $defaultsFile = tempnam(sys_get_temp_dir(), 'mysqldump_defaults_');
        file_put_contents($defaultsFile, sprintf("[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n", $user, $password, $host, $port));
        chmod($defaultsFile, 0600);

        try {
            $process = new Process([
                'mysqldump',
                '--defaults-extra-file='.$defaultsFile,
                '--single-transaction',
                '--routines',
                '--triggers',
                $dbName,
            ]);
            $process->setTimeout(600);
            $process->run();

            if (!$process->isSuccessful()) {
                $io->error('Échec de mysqldump : '.$process->getErrorOutput());

                return Command::FAILURE;
            }

            $dump = $process->getOutput();
        } finally {
            $filesystem->remove($defaultsFile);
        }

        if ('' === $dump) {
            $io->error('La sauvegarde générée est vide.');

            return Command::FAILURE;
        }

        $timestamp = (new \DateTimeImmutable())->format('Y-m-d_His');
        $dumpPath = sprintf('%s/%s_%s.sql.gz', $outputDir, $dbName, $timestamp);

        file_put_contents($dumpPath, gzencode($dump, 9));
        // Contient des données potentiellement sensibles (emails, hashs de
        // mot de passe...) : lecture restreinte au propriétaire du fichier.
        chmod($dumpPath, 0600);

        $io->success(sprintf('Sauvegarde créée : %s (%s)', $dumpPath, $this->formatBytes(filesize($dumpPath) ?: 0)));

        $this->pruneOldBackups($io, $filesystem, $outputDir, $dbName, $keep);

        return Command::SUCCESS;
    }

    private function pruneOldBackups(SymfonyStyle $io, Filesystem $filesystem, string $outputDir, string $dbName, int $keep): void
    {
        $pattern = sprintf('%s/%s_*.sql.gz', $outputDir, $dbName);
        $files = glob($pattern) ?: [];
        // Le nom de fichier contient un timestamp Y-m-d_His, donc triable
        // lexicographiquement : le plus récent en premier une fois inversé.
        rsort($files);

        $toDelete = array_slice($files, $keep);
        foreach ($toDelete as $file) {
            $filesystem->remove($file);
        }

        if ([] !== $toDelete) {
            $io->comment(sprintf('%d ancienne(s) sauvegarde(s) supprimée(s) (rétention : %d).', count($toDelete), $keep));
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['o', 'Ko', 'Mo', 'Go'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            ++$i;
        }

        return sprintf('%.1f %s', $value, $units[$i]);
    }
}
