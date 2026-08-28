<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\ProjectExpense;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV des factures et dépenses pour le module Finance — destiné à un
 * comptable ou un tableur, pas à un affichage : les montants restent en
 * décimal brut non localisé (jamais passés par le filtre Twig `money`).
 *
 * Délimiteur `;` (pas `,`) : Excel en locale FR interprète la virgule comme
 * séparateur décimal, ce qui casse l'import d'un CSV à virgules. Le flux est
 * préfixé d'un BOM UTF-8, sans quoi Excel affiche mal les caractères
 * accentués.
 */
final class FinanceCsvExporter
{
    private const DELIMITER = ';';

    /** @return string[] */
    public function invoiceHeaders(): array
    {
        return ['Numéro', 'Projet', 'Client', 'Libellé', 'Montant', 'Devise', 'Statut', 'Émise le', 'Échéance', 'Payée le'];
    }

    /** @return string[] */
    public function invoiceRow(Invoice $invoice): array
    {
        $project = $invoice->getProject();
        $client = $project->getClient();

        return [
            $invoice->getNumber(),
            $project->getTitle(),
            $client ? ($client->getFullName() ?: $client->getEmail()) : '',
            $invoice->getLabel(),
            $invoice->getAmount(),
            $invoice->getCurrency(),
            $invoice->getStatus()->getLabel(),
            $invoice->getIssuedAt()->format('Y-m-d'),
            $invoice->getDueDate()?->format('Y-m-d') ?? '',
            $invoice->getPaidAt()?->format('Y-m-d') ?? '',
        ];
    }

    /** @return string[] */
    public function expenseHeaders(): array
    {
        return ['Projet', 'Catégorie', 'Description', 'Montant (EUR)', 'Statut', 'Auteur', 'Date de dépense', 'Approuvé par', 'Approuvée le'];
    }

    /** @return string[] */
    public function expenseRow(ProjectExpense $expense): array
    {
        $approvedBy = $expense->getApprovedBy();

        return [
            $expense->getProject()->getTitle(),
            $expense->getCategory()->getLabel(),
            $expense->getDescription() ?? '',
            $expense->getAmount(),
            $expense->getStatus()->getLabel(),
            $expense->getUser()->getFullName() ?: $expense->getUser()->getEmail(),
            $expense->getEffectiveDate()->format('Y-m-d'),
            $approvedBy ? ($approvedBy->getFullName() ?: $approvedBy->getEmail()) : '',
            $expense->getApprovedAt()?->format('Y-m-d') ?? '',
        ];
    }

    /**
     * @param string[]          $headers
     * @param iterable<mixed[]> $rows
     */
    public function stream(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            // escape: '' — désactive l'échappement antéslash historique de PHP
            // (non conforme RFC 4180 et déprécié en 8.4 s'il reste implicite) ;
            // les champs restent encadrés et les guillemets internes doublés.
            fputcsv($handle, $headers, self::DELIMITER, escape: '');

            foreach ($rows as $row) {
                fputcsv($handle, $row, self::DELIMITER, escape: '');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }
}
