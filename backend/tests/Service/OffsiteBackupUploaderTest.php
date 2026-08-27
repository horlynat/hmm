<?php

namespace App\Tests\Service;

use App\Service\OffsiteBackupUploader;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class OffsiteBackupUploaderTest extends TestCase
{
    public function testIsConfiguredRequiresAllFourValues(): void
    {
        self::assertFalse((new OffsiteBackupUploader(new NullLogger(), '/tmp'))->isConfigured());

        self::assertTrue((new OffsiteBackupUploader(
            new NullLogger(),
            '/tmp',
            endpoint: 'https://example.r2.cloudflarestorage.com',
            bucket: 'hmm-backups',
            accessKeyId: 'id',
            secretAccessKey: 'secret',
        ))->isConfigured());
    }

    public function testUploadFileNoOpsWithoutNetworkCallWhenNotConfigured(): void
    {
        // Rien n'est configuré : ni uploadFile() ni upload() ne doivent tenter
        // de construire un S3Client (qui échouerait ou tenterait un appel
        // réseau) — juste un log et un retour silencieux.
        $uploader = new OffsiteBackupUploader(new NullLogger(), sys_get_temp_dir());

        $uploader->uploadFile('/does/not/matter.sql.gz.age', 'database/whatever.sql.gz.age');
        $uploader->upload('some/relative/path.jpg');

        $this->addToAssertionCount(1); // aucune exception : c'est le test.
    }

    public function testUploadFileNoOpsWhenConfiguredButLocalFileMissing(): void
    {
        // Configuré, mais le fichier local n'existe pas : doit s'arrêter
        // avant de construire un S3Client, pas planter dessus.
        $uploader = new OffsiteBackupUploader(
            new NullLogger(),
            sys_get_temp_dir(),
            endpoint: 'https://example.r2.cloudflarestorage.com',
            bucket: 'hmm-backups',
            accessKeyId: 'id',
            secretAccessKey: 'secret',
        );

        $uploader->uploadFile('/definitely/not/a/real/file-' . uniqid('', true) . '.sql.gz.age', 'database/x.sql.gz.age');

        $this->addToAssertionCount(1);
    }

    public function testPruneOldObjectsNoOpsWithoutNetworkCallWhenNotConfigured(): void
    {
        // Même garde que uploadFile()/upload() : pas de S3Client construit
        // tant que le provider n'est pas configuré.
        $uploader = new OffsiteBackupUploader(new NullLogger(), sys_get_temp_dir());

        $uploader->pruneOldObjects('database/', 5);

        $this->addToAssertionCount(1);
    }

    public function testUploadDelegatesToUploadFileWithJoinedPath(): void
    {
        // upload() doit rester le raccourci "relatif à uploadDir" existant :
        // avec un provider non configuré, on vérifie juste qu'il ne casse pas
        // et se comporte comme uploadFile() (même no-op).
        $uploadDir = sys_get_temp_dir() . '/offsite-uploader-test-' . uniqid('', true);
        mkdir($uploadDir);
        try {
            $uploader = new OffsiteBackupUploader(new NullLogger(), $uploadDir);
            $uploader->upload('sub/dir/file.jpg');
            $this->addToAssertionCount(1);
        } finally {
            rmdir($uploadDir);
        }
    }
}
