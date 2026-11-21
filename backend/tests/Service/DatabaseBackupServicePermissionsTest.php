<?php

namespace App\Tests\Service;

use App\Service\AdminAlertNotifier;
use App\Service\DatabaseBackupService;
use App\Service\OffsiteBackupUploader;
use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Notifier\Notifier;

/**
 * Dumps SQL en clair : jamais lisibles par le groupe ni les autres
 * (audit 2026-11-20, P1 #15). `mysqldump` est remplacé par un faux binaire
 * (PATH) qui crée le fichier comme le vrai : open(O_CREAT, 0660) soumis à
 * l'umask du processus — c'est donc l'umask hérité qu'on vérifie.
 */
final class DatabaseBackupServicePermissionsTest extends KernelTestCase
{
    private string $dir;
    private string $binDir;
    private string|false $previousPath;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir().'/hmm-backup-test-'.bin2hex(random_bytes(4));
        $this->dir = $base.'/backups';
        $this->binDir = $base.'/bin';
        mkdir($this->binDir, 0700, true);
        file_put_contents($this->binDir.'/mysqldump', <<<'SH'
            #!/bin/sh
            for arg in "$@"; do
                case "$arg" in --result-file=*) file="${arg#--result-file=}" ;; esac
            done
            exec python3 -I -c 'import os,sys; fd=os.open(sys.argv[1], os.O_CREAT|os.O_WRONLY|os.O_TRUNC, 0o660); os.write(fd, b"-- dump\n"); os.close(fd)' "$file"
            SH);
        chmod($this->binDir.'/mysqldump', 0700);
        $this->previousPath = getenv('PATH');
        putenv('PATH='.$this->binDir.':'.$this->previousPath);
    }

    protected function tearDown(): void
    {
        putenv('PATH='.$this->previousPath);
        (new Filesystem())->remove(\dirname($this->dir));
        parent::tearDown();
    }

    public function testDumpAndDirectoryArePrivateAndWorkerUmaskIsRestored(): void
    {
        self::bootKernel();
        $umaskBefore = umask(0022);

        try {
            $service = new DatabaseBackupService(
                self::getContainer()->get(Connection::class),
                $this->dir,
                new AdminAlertNotifier(new Notifier([]), new NullLogger()),
                new OffsiteBackupUploader(new NullLogger(), sys_get_temp_dir()),
                new NullLogger(),
            );

            $filename = $service->create();

            self::assertSame(0700, fileperms($this->dir) & 0777, 'Dossier de sauvegardes en 0700.');
            self::assertSame(0600, fileperms($this->dir.'/'.$filename) & 0777, 'Dump en 0600 (aucun accès groupe/autres).');
            self::assertSame(0022, umask(), "L'umask du worker est restauré après la création du processus.");
        } finally {
            umask($umaskBefore);
        }
    }
}
