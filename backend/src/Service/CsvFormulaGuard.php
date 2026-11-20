<?php

namespace App\Service;

/**
 * Neutralise l'injection de formules dans les exports CSV du back-office
 * (CSV/formula injection, audit 2026-11-20 P2) : une cellule qui commence
 * par `=`, `+`, `-`, `@`, tabulation ou retour chariot est interprétée comme
 * une formule par Excel/LibreOffice à l'ouverture (`=HYPERLINK(…)`,
 * `=WEBSERVICE(…)`, DDE…). Plusieurs exports contiennent des valeurs
 * contrôlées par un anonyme : email tenté au login, User-Agent, titre de
 * ticket… Ces cellules sont préfixées d'une apostrophe, qui force le texte.
 *
 * Les nombres (int/float, ou chaîne décimale simple comme `-12.50`)
 * ne sont pas touchés : ils ne peuvent pas porter de formule, et les
 * préfixer casserait les montants négatifs dans le tableur.
 *
 * Point d'entrée unique de TOUS les exports CSV : utiliser putRow() plutôt
 * que fputcsv() directement.
 */
final class CsvFormulaGuard
{
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public static function cell(mixed $value): mixed
    {
        // Nombre décimal simple uniquement (pas is_numeric(), qui accepte des
        // blancs en tête comme "\t-1" ou des exposants).
        if (!\is_string($value) || '' === $value || 1 === preg_match('/^-?\d+(?:[.,]\d+)?$/', $value)) {
            return $value;
        }

        return \in_array($value[0], self::DANGEROUS_PREFIXES, true) ? "'".$value : $value;
    }

    /**
     * fputcsv() avec chaque cellule neutralisée — mêmes options que les
     * exporteurs (escape: '' : RFC 4180, guillemets doublés).
     *
     * @param resource     $handle
     * @param array<mixed> $row
     */
    public static function putRow($handle, array $row, string $delimiter): void
    {
        fputcsv($handle, array_map(self::cell(...), $row), $delimiter, escape: '');
    }
}
