<?php

namespace App\Command;

use App\Service\MediaUploader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Repasse TOUS les SVG déjà stockés dans le dossier d'uploads au nettoyeur
 * actuel de MediaUploader (même politique qu'à l'upload, cf.
 * MediaUploader::sanitizeSvg) — les fichiers déposés avant la montée de
 * enshrined/svg-sanitize (CVE-2026-107380 et suivantes) n'ont été nettoyés
 * que par l'ancienne version.
 *
 * - Par défaut : simulation (aucune écriture), rapport fichier par fichier.
 * - `--apply` : réécrit les fichiers modifiés (écriture atomique, droits
 *   conservés) et déplace les fichiers REFUSÉS par le nettoyeur dans un
 *   dossier de quarantaine HORS de public/ (jamais servi), en conservant
 *   leur chemin relatif. Les références en base vers un fichier mis en
 *   quarantaine deviennent des liens cassés : le rapport les liste pour
 *   correction manuelle.
 * - Idempotente : le nettoyage d'un SVG déjà nettoyé est l'identité, une
 *   seconde exécution ne modifie plus rien.
 * Les liens symboliques et fichiers non réguliers sont ignorés (jamais
 * suivis).
 *
 * Exécution en production (dans le conteneur backend, en www-data,
 * propriétaire du volume d'uploads) :
 *   docker compose exec -u www-data backend php bin/console app:media:resanitize-svg
 *   docker compose exec -u www-data backend php bin/console app:media:resanitize-svg --apply
 */
#[AsCommand(name: 'app:media:resanitize-svg', description: 'Repasse les SVG du dossier d\'uploads au nettoyeur actuel (simulation par défaut, --apply pour écrire).')]
final class MediaResanitizeSvgCommand extends Command
{
    public function __construct(
        private readonly MediaUploader $mediaUploader,
        #[Autowire('%app.upload_dir%')] private readonly string $uploadDir,
        #[Autowire('%kernel.project_dir%/var/quarantine/svg')] private readonly string $quarantineDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Écrit les fichiers nettoyés et met en quarantaine les fichiers refusés (sinon : simulation).')
            ->addOption('quarantine-dir', null, InputOption::VALUE_REQUIRED, 'Dossier de quarantaine (doit être hors de public/).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');
        $quarantineDir = rtrim((string) ($input->getOption('quarantine-dir') ?? $this->quarantineDir), '/');
        $root = realpath($this->uploadDir);

        if (false === $root || !is_dir($root)) {
            $io->error(sprintf('Dossier d\'uploads introuvable : %s', $this->uploadDir));

            return Command::FAILURE;
        }

        if ($apply) {
            // Créé d'abord pour pouvoir le résoudre (realpath : `..`, liens) :
            // une quarantaine sous public/ laisserait les fichiers refusés servis.
            if (!is_dir($quarantineDir) && !@mkdir($quarantineDir, 0700, true) && !is_dir($quarantineDir)) {
                $io->error(sprintf('Dossier de quarantaine impossible à créer : %s', $quarantineDir));

                return Command::FAILURE;
            }
            $quarantineDir = (string) realpath($quarantineDir);
            if (str_starts_with($quarantineDir.'/', \dirname($root).'/')) {
                $io->error('Le dossier de quarantaine ne doit pas se trouver sous le dossier public servi.');

                return Command::FAILURE;
            }
        }

        $io->title(sprintf('Re-nettoyage des SVG de %s %s', $root, $apply ? '(APPLICATION)' : '(simulation, --apply pour écrire)'));

        $counts = ['unchanged' => 0, 'sanitized' => 0, 'refused' => 0, 'errors' => 0];
        $refused = [];

        foreach ($this->findSvgFiles($root) as $path) {
            $relative = substr($path, \strlen($root) + 1);
            $raw = @file_get_contents($path);

            if (false === $raw) {
                ++$counts['errors'];
                $io->writeln(sprintf('<error>ERREUR</error>   %s (illisible)', $relative));
                continue;
            }

            try {
                $clean = $this->mediaUploader->sanitizeSvg($raw);
            } catch (\RuntimeException $e) {
                ++$counts['refused'];
                $refused[] = $relative;
                $io->writeln(sprintf('<comment>REFUSÉ</comment>   %s — %s', $relative, $e->getMessage()));

                if ($apply && !$this->quarantine($path, $quarantineDir.'/'.$relative)) {
                    ++$counts['errors'];
                    $io->writeln(sprintf('<error>ERREUR</error>   %s (mise en quarantaine impossible)', $relative));
                }
                continue;
            }

            if ($clean === $raw) {
                ++$counts['unchanged'];
                $io->writeln(sprintf('OK       %s', $relative), OutputInterface::VERBOSITY_VERBOSE);
                continue;
            }

            ++$counts['sanitized'];
            $io->writeln(sprintf('<info>NETTOYÉ</info>  %s (%d → %d octets)', $relative, \strlen($raw), \strlen($clean)));

            if ($apply && !$this->atomicWrite($path, $clean)) {
                ++$counts['errors'];
                $io->writeln(sprintf('<error>ERREUR</error>   %s (réécriture impossible)', $relative));
            }
        }

        $io->newLine();
        $io->table(['Inchangés', $apply ? 'Nettoyés' : 'À nettoyer', $apply ? 'Mis en quarantaine' : 'À mettre en quarantaine', 'Erreurs'], [[
            $counts['unchanged'], $counts['sanitized'], $counts['refused'], $counts['errors'],
        ]]);

        if ([] !== $refused) {
            $io->warning(array_merge(
                [sprintf('Fichiers refusés par le nettoyeur%s — les références en base vers ces chemins (/uploads/…) sont à corriger :', $apply ? ' (déplacés dans '.$quarantineDir.')' : '')],
                $refused,
            ));
        }

        if ($counts['errors'] > 0) {
            return Command::FAILURE;
        }

        $io->success($apply ? 'Terminé.' : 'Simulation terminée — relancer avec --apply pour appliquer.');

        return Command::SUCCESS;
    }

    /** @return iterable<string> */
    private function findSvgFiles(string $root): iterable
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_FILEINFO),
        );

        $paths = [];
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isLink() || !$file->isFile() || 'svg' !== strtolower($file->getExtension())) {
                continue;
            }
            $paths[] = $file->getPathname();
        }
        sort($paths);

        return $paths;
    }

    private function atomicWrite(string $path, string $content): bool
    {
        $mode = fileperms($path) & 0777;
        $tmp = \dirname($path).'/.'.basename($path).'.'.bin2hex(random_bytes(4)).'.tmp';

        if (false === @file_put_contents($tmp, $content)) {
            return false;
        }
        @chmod($tmp, $mode ?: 0644);

        if (!@rename($tmp, $path)) {
            @unlink($tmp);

            return false;
        }

        return true;
    }

    private function quarantine(string $path, string $destination): bool
    {
        $dir = \dirname($destination);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return false;
        }

        if (file_exists($destination)) {
            $destination .= '.'.date('YmdHis');
        }

        // rename() échoue entre deux systèmes de fichiers (volume d'uploads ≠ var/) : copie + suppression.
        if (@rename($path, $destination)) {
            return true;
        }

        if (!@copy($path, $destination)) {
            return false;
        }
        @chmod($destination, 0600);

        return @unlink($path);
    }
}
