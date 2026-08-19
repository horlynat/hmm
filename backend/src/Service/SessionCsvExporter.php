<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV des sessions actives — même mécanique que FinanceCsvExporter
 * (délimiteur `;` pour Excel FR, BOM UTF-8 pour les accents), volontairement
 * dupliquée plutôt que partagée : les deux exporteurs n'ont que le
 * "streamer" CSV générique en commun (extrait ci-dessous), pas les colonnes.
 */
final class SessionCsvExporter
{
    private const DELIMITER = ';';

    /** @return string[] */
    public function headers(): array
    {
        return ['Compte', 'Email', 'IP', 'Appareil', 'Localisation', 'Dernière activité', 'Ouverte le', 'Statut'];
    }

    /**
     * @param array{session: \App\Entity\UserSession, state: string, lastActivityAt: ?\DateTimeImmutable, device: array{label: string}, location: ?string} $entry
     *
     * @return string[]
     */
    public function row(array $entry): array
    {
        $session = $entry['session'];
        $user = $session->getUser();

        return [
            $user->getFullName() ?? '',
            $user->getEmail(),
            $session->getIp() ?? '',
            $entry['device']['label'],
            $entry['location'] ?? '',
            $entry['lastActivityAt']?->format('Y-m-d H:i:s') ?? '',
            $session->getCreatedAt()->format('Y-m-d H:i:s'),
            match ($entry['state']) {
                'active' => 'Active',
                'expired' => 'Expirée',
                default => 'Terminée',
            },
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
