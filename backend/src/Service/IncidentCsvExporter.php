<?php

namespace App\Service;

use App\Entity\Incident;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV du journal d'incidents — même mécanique que
 * SecurityLogCsvExporter/SessionCsvExporter/FinanceCsvExporter (délimiteur
 * `;` pour Excel FR, BOM UTF-8 pour les accents).
 */
final class IncidentCsvExporter
{
    private const DELIMITER = ';';

    /** @return string[] */
    public function headers(): array
    {
        return ['Titre', 'Catégorie', 'Gravité', 'Statut', 'Détecté le', 'Résolu le', 'Signalé par', 'Référence'];
    }

    /** @return string[] */
    public function row(Incident $incident): array
    {
        return [
            $incident->getTitle(),
            $incident->getCategory()->getLabel(),
            $incident->getSeverity()->getLabel(),
            $incident->getStatus()->getLabel(),
            $incident->getDetectedAt()->format('Y-m-d H:i:s'),
            $incident->getResolvedAt()?->format('Y-m-d H:i:s') ?? '',
            $incident->getReportedBy()?->getEmail() ?? '',
            $incident->getRelatedReference() ?? '',
        ];
    }

    /**
     * @param iterable<array<string>> $rows
     */
    public function stream(string $filename, iterable $rows): StreamedResponse
    {
        $headers = $this->headers();
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
