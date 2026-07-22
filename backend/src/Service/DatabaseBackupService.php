<?php

namespace App\Service;

use App\Enum\NotificationPriorityEnum;
use Doctrine\DBAL\Connection;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Sauvegarde / restauration de la base de données via mysqldump / mysql.
 *
 * Seul le pilote MySQL est supporté (c'est celui utilisé en production, cf.
 * DATABASE_URL) : create() échoue explicitement pour tout autre pilote plutôt
 * que de produire une sauvegarde silencieusement incomplète.
 *
 * Le mot de passe transite par la variable d'environnement MYSQL_PWD (jamais
 * en argument de ligne de commande, pour éviter qu'il n'apparaisse dans `ps`).
 */
final class DatabaseBackupService
{
    private const FILENAME_REGEX = '/^backup_\d{8}_\d{6}\.sql$/';

    public function __construct(
        private readonly Connection $connection,
        private readonly string $backupDir,
        private readonly AdminAlertNotifier $adminAlertNotifier,
    ) {
        if (!is_dir($this->backupDir) && !mkdir($this->backupDir, 0775, true) && !is_dir($this->backupDir)) {
            throw new \RuntimeException(sprintf('Impossible de créer le répertoire de sauvegardes "%s".', $this->backupDir));
        }
    }

    public function create(): string
    {
        $params = $this->assertMysqlDriver();

        $filename = sprintf('backup_%s.sql', (new \DateTimeImmutable())->format('Ymd_His'));
        $filepath = $this->backupDir . '/' . $filename;

        $process = new Process([
            'mysqldump',
            '--host=' . ($params['host'] ?? '127.0.0.1'),
            '--port=' . (string) ($params['port'] ?? 3306),
            '--user=' . ($params['user'] ?? 'root'),
            '--single-transaction',
            '--skip-lock-tables',
            '--result-file=' . $filepath,
            (string) $params['dbname'],
        ]);
        $process->setEnv(['MYSQL_PWD' => (string) ($params['password'] ?? '')]);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            if (is_file($filepath)) {
                unlink($filepath);
            }

            $this->adminAlertNotifier->alert(
                NotificationPriorityEnum::HIGH,
                'Échec de la sauvegarde de la base de données',
                $process->getErrorOutput() ?: 'mysqldump a échoué sans message d\'erreur.',
            );

            throw new ProcessFailedException($process);
        }

        return $filename;
    }

    /**
     * @return array<int, array{filename: string, size: int, createdAt: \DateTimeImmutable}>
     */
    public function list(): array
    {
        $files = glob($this->backupDir . '/backup_*.sql') ?: [];
        rsort($files);

        return array_map(
            static fn (string $file): array => [
                'filename' => basename($file),
                'size' => filesize($file) ?: 0,
                'createdAt' => (new \DateTimeImmutable())->setTimestamp(filemtime($file) ?: time()),
            ],
            $files,
        );
    }

    public function delete(string $filename): void
    {
        unlink($this->resolvePath($filename));
    }

    public function getPath(string $filename): string
    {
        return $this->resolvePath($filename);
    }

    public function restore(string $filename): void
    {
        $filepath = $this->resolvePath($filename);
        $params = $this->assertMysqlDriver();

        $handle = fopen($filepath, 'rb');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('Impossible de lire le fichier de sauvegarde "%s".', $filename));
        }

        try {
            $process = new Process([
                'mysql',
                '--host=' . ($params['host'] ?? '127.0.0.1'),
                '--port=' . (string) ($params['port'] ?? 3306),
                '--user=' . ($params['user'] ?? 'root'),
                (string) $params['dbname'],
            ]);
            $process->setEnv(['MYSQL_PWD' => (string) ($params['password'] ?? '')]);
            $process->setInput($handle);
            $process->setTimeout(600);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->adminAlertNotifier->alert(
                    NotificationPriorityEnum::URGENT,
                    'Échec de la restauration de la base de données',
                    sprintf('La restauration depuis "%s" a échoué : %s', $filename, $process->getErrorOutput() ?: 'erreur inconnue.'),
                );

                throw new ProcessFailedException($process);
            }
        } finally {
            if (\is_resource($handle)) {
                fclose($handle);
            }
        }

        // Action irréversible et destructrice : alerte même en cas de succès, à
        // titre de piste d'audit (cf. AdminBackupController::restore, réservé SUPER_ADMIN).
        $this->adminAlertNotifier->alert(
            NotificationPriorityEnum::URGENT,
            'Base de données restaurée',
            sprintf('La base de données a été restaurée depuis "%s".', $filename),
        );
    }

    /**
     * @return array{driver?: string, host?: string, port?: int, user?: string, password?: string, dbname?: string}
     */
    private function assertMysqlDriver(): array
    {
        $params = $this->connection->getParams();
        $driver = $params['driver'] ?? null;

        if (!\in_array($driver, ['pdo_mysql', 'mysqli'], true)) {
            throw new \RuntimeException(sprintf('Sauvegarde/restauration non supportée pour le pilote "%s" (seul MySQL est géré).', $driver ?? 'inconnu'));
        }

        if (empty($params['dbname'])) {
            throw new \RuntimeException('Impossible de déterminer le nom de la base de données.');
        }

        return $params;
    }

    /** Valide le nom de fichier (empêche toute traversée de chemin) et vérifie son existence. */
    private function resolvePath(string $filename): string
    {
        if (1 !== preg_match(self::FILENAME_REGEX, $filename)) {
            throw new \InvalidArgumentException('Nom de fichier de sauvegarde invalide.');
        }

        $filepath = $this->backupDir . '/' . $filename;
        if (!is_file($filepath)) {
            throw new \RuntimeException(sprintf('Le fichier de sauvegarde "%s" est introuvable.', $filename));
        }

        return $filepath;
    }
}
