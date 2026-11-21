<?php

namespace App\Tests\Command;

use App\Command\MediaResanitizeSvgCommand;
use App\Service\MediaUploader;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * app:media:resanitize-svg : SVG stockés avant la montée du nettoyeur
 * (audit 2026-11-20, P0 #2 / L1). Simulation par défaut, --apply idempotent,
 * fichiers refusés mis en quarantaine hors de public/.
 */
final class MediaResanitizeSvgCommandTest extends TestCase
{
    private const NS = 'xmlns="http://www.w3.org/2000/svg"';

    private string $base;
    private string $uploads;
    private string $quarantine;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir().'/hmm-resanitize-'.bin2hex(random_bytes(4));
        $this->uploads = $this->base.'/public/uploads';
        $this->quarantine = $this->base.'/var/quarantine';
        mkdir($this->uploads.'/branding', 0755, true);
        mkdir($this->uploads.'/projects', 0755, true);

        file_put_contents($this->uploads.'/branding/evil.svg', '<svg '.self::NS.' onload="alert(1)"><script>alert(2)</script><circle r="4"/></svg>');
        file_put_contents($this->uploads.'/projects/xxe.SVG', '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg '.self::NS.'><text>&x;</text></svg>');
        file_put_contents($this->uploads.'/projects/photo.jpg', 'not an svg, untouched');
        chmod($this->uploads.'/branding/evil.svg', 0644);
        // Lien symbolique vers un fichier hors du dossier : jamais suivi.
        file_put_contents($this->base.'/outside.svg', '<svg '.self::NS.'><script>alert(3)</script></svg>');
        symlink($this->base.'/outside.svg', $this->uploads.'/projects/link.svg');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->base);
    }

    private function tester(?string $quarantine = null): CommandTester
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static fn (object $m): Envelope => new Envelope($m));
        $uploader = new MediaUploader($this->uploads, new AsciiSlugger(), new NullLogger(), $bus, new RequestStack(), ['image/svg+xml']);

        return new CommandTester(new MediaResanitizeSvgCommand($uploader, $this->uploads, $quarantine ?? $this->quarantine));
    }

    public function testDryRunReportsWithoutWriting(): void
    {
        $before = (string) file_get_contents($this->uploads.'/branding/evil.svg');

        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();
        self::assertStringContainsString('branding/evil.svg', $display);
        self::assertStringContainsString('projects/xxe.SVG', $display);
        self::assertStringNotContainsString('link.svg', $display);
        self::assertSame($before, file_get_contents($this->uploads.'/branding/evil.svg'));
        self::assertFileExists($this->uploads.'/projects/xxe.SVG');
        self::assertDirectoryDoesNotExist($this->quarantine);
    }

    public function testApplySanitizesQuarantinesAndIsIdempotent(): void
    {
        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute(['--apply' => true]));

        $clean = (string) file_get_contents($this->uploads.'/branding/evil.svg');
        self::assertStringNotContainsString('<script', $clean);
        self::assertStringNotContainsString('onload', $clean);
        self::assertStringContainsString('<circle', $clean);
        self::assertSame(0644, fileperms($this->uploads.'/branding/evil.svg') & 0777, 'Droits conservés.');

        // XXE refusé : retiré de public/, conservé en quarantaine avec son chemin relatif.
        self::assertFileDoesNotExist($this->uploads.'/projects/xxe.SVG');
        self::assertFileExists($this->quarantine.'/projects/xxe.SVG');

        // Rien d'autre touché ; lien symbolique ni suivi ni modifié.
        self::assertSame('not an svg, untouched', file_get_contents($this->uploads.'/projects/photo.jpg'));
        self::assertStringContainsString('<script>alert(3)', (string) file_get_contents($this->base.'/outside.svg'));
        self::assertSame([], glob($this->uploads.'/branding/.*.tmp') ?: []);

        // Seconde passe : plus rien à faire.
        $second = $this->tester();
        self::assertSame(Command::SUCCESS, $second->execute(['--apply' => true]));
        self::assertSame($clean, file_get_contents($this->uploads.'/branding/evil.svg'));
        self::assertMatchesRegularExpression('/^\s*1\s+0\s+0\s+0\s*$/m', $second->getDisplay());
    }

    public function testQuarantineInsidePublicIsRejected(): void
    {
        $tester = $this->tester($this->uploads.'/../quarantine');
        self::assertSame(Command::FAILURE, $tester->execute(['--apply' => true]));
        self::assertFileExists($this->uploads.'/projects/xxe.SVG');
    }
}
