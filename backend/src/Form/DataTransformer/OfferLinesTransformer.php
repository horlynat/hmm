<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Listes des offres éditées en zone de texte, un élément par ligne :
 *  - MODE_LIST     : « élément »                         → list<string>
 *  - MODE_CALENDAR : « J1 | texte »                       → list<{day, text}>
 *  - MODE_PACK     : « Titre : élément ; élément ; … »    → list<{title, items}>
 *
 * Une ligne mal formée lève TransformationFailedException : le formulaire
 * affiche son `invalid_message`, jamais une 500. Les bornes (nombre, longueur)
 * restent celles de l'entité (App\Entity\Offer), validées ensuite.
 *
 * @implements DataTransformerInterface<array<mixed>|null, string>
 */
final class OfferLinesTransformer implements DataTransformerInterface
{
    public const MODE_LIST = 'list';
    public const MODE_CALENDAR = 'calendar';
    public const MODE_PACK = 'pack';

    /** Garde-fou avant découpage : bien au-delà de toute liste légitime. */
    private const MAX_INPUT = 20_000;

    public function __construct(
        private readonly string $mode,
        private readonly bool $nullable,
    ) {
    }

    public function transform(mixed $value): string
    {
        if (!\is_array($value)) {
            return '';
        }

        $lines = [];
        foreach ($value as $row) {
            $lines[] = match ($this->mode) {
                self::MODE_CALENDAR => \is_array($row) ? \sprintf('%s | %s', $row['day'] ?? '', $row['text'] ?? '') : '',
                self::MODE_PACK => \is_array($row) ? \sprintf('%s : %s', $row['title'] ?? '', implode(' ; ', array_filter((array) ($row['items'] ?? []), 'is_string'))) : '',
                default => \is_string($row) ? $row : '',
            };
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<mixed>|null
     */
    public function reverseTransform(mixed $value): ?array
    {
        if (null === $value || '' === trim((string) $value)) {
            return $this->nullable ? null : [];
        }
        // $value est une chaîne : contrat de l'interface, et Symfony refuse en
        // amont un tableau soumis à un champ texte (testé : 422, pas 500).
        if (\strlen($value) > self::MAX_INPUT) {
            throw new TransformationFailedException('Texte trop long.');
        }

        $rows = [];
        foreach (preg_split('/\R/u', $value) ?: [] as $n => $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }
            $rows[] = match ($this->mode) {
                self::MODE_CALENDAR => $this->calendarRow($line, $n + 1),
                self::MODE_PACK => $this->packRow($line, $n + 1),
                default => $line,
            };
        }

        return $rows;
    }

    /**
     * @return array{day: string, text: string}
     */
    private function calendarRow(string $line, int $n): array
    {
        $parts = explode('|', $line, 2);
        if (2 !== \count($parts) || '' === trim($parts[0]) || '' === trim($parts[1])) {
            throw new TransformationFailedException(\sprintf('Ligne %d : format attendu « J1 | texte ».', $n));
        }

        return ['day' => trim($parts[0]), 'text' => trim($parts[1])];
    }

    /**
     * @return array{title: string, items: list<string>}
     */
    private function packRow(string $line, int $n): array
    {
        $parts = explode(':', $line, 2);
        $items = 2 === \count($parts) ? array_values(array_filter(array_map('trim', explode(';', $parts[1])), static fn (string $s): bool => '' !== $s)) : [];
        if (2 !== \count($parts) || '' === trim($parts[0]) || [] === $items) {
            throw new TransformationFailedException(\sprintf('Ligne %d : format attendu « Titre : élément ; élément ».', $n));
        }

        return ['title' => trim($parts[0]), 'items' => $items];
    }
}
