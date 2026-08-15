<?php

namespace App\Service;

/**
 * Extrait un montant décimal exploitable du champ budget en texte libre d'un
 * QuoteRequest (ex. "5000", "5 000 €", "5000-8000") — ou null si non
 * interprétable. Partagé entre AdminQuoteRequestController::convert() (budget
 * du projet créé) et ProjectStatisticsService (estimation du pipeline
 * commercial) : les deux doivent s'accorder sur la même lecture d'un même
 * texte, sinon les chiffres affichés se contredisent d'un endroit à l'autre.
 */
final class QuoteBudgetParser
{
    public function parse(?string $raw): ?string
    {
        if (null === $raw || '' === trim($raw)) {
            return null;
        }

        // Ne garde que le premier nombre trouvé (utile pour les fourchettes "5000-8000" → 5000).
        if (!preg_match('/\d[\d\s]*(?:[.,]\d+)?/', $raw, $matches)) {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], $matches[0]);
        if (!is_numeric($normalized) || (float) $normalized <= 0) {
            return null;
        }

        return number_format((float) $normalized, 2, '.', '');
    }
}
