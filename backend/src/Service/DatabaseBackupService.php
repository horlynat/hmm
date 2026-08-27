<?php

namespace App\Service;

use App\Enum\NotificationPriorityEnum;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
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
 *
 * Copie hors-site : var/backups n'est qu'un volume Docker du VPS — perdre le
 * VPS perd aussi ce dossier. Après chaque dump réussi, create() tente en plus
 * une copie chiffrée (gzip + age) vers OffsiteBackupUploader (même bucket
 * S3-compatible que les médias, préfixe "database/"). Best-effort : un échec
 * de cette étape alerte (AdminAlertNotifier) mais ne fait jamais échouer
 * create() — la sauvegarde locale, elle, a déjà réussi. Se désactive
 * proprement (log, pas d'erreur) tant qu'AGE_RECIPIENT ou le provider S3 ne
 * sont pas configurés.
 */
final class DatabaseBackupService
{
    private const FILENAME_REGEX = '/^backup_\d{8}_\d{6}\.sql$/';

    // Conserver plusieurs générations, pas juste la dernière : une sauvegarde
    // corrompue ou empoisonnée (attaquant ayant altéré la base avant le dump,
    // ransomware, erreur applicative) doit pouvoir être contournée en
    // remontant d'un ou plusieurs crans, pas seulement détectée après coup
    // sans recours. Local ET hors-site (cf. shipOffsite) — un seul des deux
    // qui purge tout à la première génération ne protégerait pas contre une
    // corruption découverte après plusieurs jours.
    private const KEEP_COUNT = 5;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $backupDir,
        private readonly AdminAlertNotifier $adminAlertNotifier,
        private readonly OffsiteBackupUploader $offsiteBackupUploader,
        private readonly LoggerInterface $logger,
        // Clé publique age (ex: "age1...") — jamais la clé privée, qui ne
        // doit JAMAIS vivre sur ce serveur (cf. docs/incident-data-loss.md).
        // Chaîne vide par défaut : même convention que OffsiteBackupUploader,
        // se désactive proprement tant qu'elle n'est pas configurée.
        private readonly string $ageRecipient = '',
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

        $this->shipOffsite($filepath, $filename);
        $this->pruneLocalBackups();

        return $filename;
    }

    /**
     * Purge les sauvegardes locales au-delà de KEEP_COUNT générations. Best-
     * effort au même titre que shipOffsite() : un échec ici ne doit jamais
     * remettre en cause le dump qui vient de réussir.
     */
    private function pruneLocalBackups(): void
    {
        try {
            $backups = $this->list();
            foreach (\array_slice($backups, self::KEEP_COUNT) as $backup) {
                $this->delete($backup['filename']);
            }
        } catch (\Throwable $e) {
            $this->logger->error('DatabaseBackupService : échec de la purge des sauvegardes locales.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Copie hors-site best-effort d'un dump qui vient d'être créé avec
     * succès : gzip + chiffrement age (clé publique seulement) + upload vers
     * OffsiteBackupUploader. N'importe quel échec ici est journalisé et
     * remonté par alerte, jamais propagé — create() a déjà rempli son
     * contrat (le fichier local existe).
     */
    private function shipOffsite(string $filepath, string $filename): void
    {
        if ('' === $this->ageRecipient || !$this->offsiteBackupUploader->isConfigured()) {
            $this->logger->info('DatabaseBackupService : copie hors-site ignorée (AGE_RECIPIENT ou provider S3 non configuré).', [
                'filename' => $filename,
            ]);

            return;
        }

        $gzPath = null;
        $encPath = null;

        try {
            $gzPath = tempnam(sys_get_temp_dir(), 'db_backup_gz_');
            $gzOut = gzopen($gzPath, 'wb9');
            if (false === $gzOut) {
                throw new \RuntimeException('Impossible de créer le flux gzip temporaire.');
            }
            $source = fopen($filepath, 'rb');
            if (false === $source) {
                throw new \RuntimeException(sprintf('Impossible de relire le dump "%s" pour compression.', $filepath));
            }
            try {
                while (!feof($source)) {
                    gzwrite($gzOut, fread($source, 1024 * 1024));
                }
            } finally {
                fclose($source);
                gzclose($gzOut);
            }

            $encPath = $gzPath . '.age';
            $process = new Process(['age', '-r', $this->ageRecipient, '-o', $encPath, $gzPath]);
            $process->setTimeout(120);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->offsiteBackupUploader->uploadFile($encPath, 'database/' . $filename . '.gz.age');
            $this->offsiteBackupUploader->pruneOldObjects('database/', self::KEEP_COUNT);
        } catch (\Throwable $e) {
            $this->logger->error('DatabaseBackupService : échec de la copie hors-site.', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);

            $this->adminAlertNotifier->alert(
                NotificationPriorityEnum::HIGH,
                'Échec de la copie hors-site de la sauvegarde',
                sprintf('La sauvegarde locale "%s" a réussi, mais sa copie hors-site a échoué : %s', $filename, $e->getMessage()),
            );
        } finally {
            // Les deux ne contiennent que des données déjà présentes dans le
            // dump local ($filepath, lui conservé) — sûr de les effacer.
            if (null !== $gzPath && is_file($gzPath)) {
                unlink($gzPath);
            }
            if (null !== $encPath && is_file($encPath)) {
                unlink($encPath);
            }
        }
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
