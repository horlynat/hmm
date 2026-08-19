<?php

namespace App\Service;

use App\Entity\FailedLoginAttempt;
use App\Entity\LoginHistory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV du journal des connexions (réussies et échouées) — même
 * mécanique que SessionCsvExporter/FinanceCsvExporter (délimiteur `;` pour
 * Excel FR, BOM UTF-8 pour les accents).
 */
final class SecurityLogCsvExporter
{
    private const DELIMITER = ';';

    /** @return string[] */
    public function successHeaders(): array
    {
        return ['Compte', 'IP', 'Appareil', 'Localisation', 'Date'];
    }

    /** @return string[] */
    public function successRow(LoginHistory $entry): array
    {
        return [
            $entry->getUser()->getEmail(),
            $entry->getIp() ?? '',
            $entry->getDevice() ?? '',
            $entry->getLocation() ?? '',
            $entry->getLoginAt()->format('Y-m-d H:i:s'),
        ];
    }

    /** @return string[] */
    public function failedHeaders(): array
    {
        return ['Email tenté', 'Motif', 'IP', 'Appareil', 'Date'];
    }

    /** @return string[] */
    public function failedRow(FailedLoginAttempt $entry): array
    {
        return [
            $entry->getEmail(),
            $entry->getReasonLabel(),
            $entry->getIp() ?? '',
            $entry->getUserAgent() ?? '',
            $entry->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param string[]                $headers
     * @param iterable<array<string>> $rows
     */
    public function stream(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, self::DELIMITER);

            foreach ($rows as $row) {
                fputcsv($handle, $row, self::DELIMITER);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }
}
