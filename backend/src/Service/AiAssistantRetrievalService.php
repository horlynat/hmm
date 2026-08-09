<?php

namespace App\Service;

use App\Entity\AiAssistantDocumentChunk;
use App\Repository\AiAssistantDocumentChunkRepository;

/**
 * Retrieval du RAG : similarité cosinus calculée en PHP entre l'embedding de
 * la question et celui de chaque chunk public — pas de pgvector (prod en
 * MySQL, pas Postgres), pas nécessaire non plus au volume de contenu d'un
 * portfolio (scan brute-force sur quelques dizaines de lignes).
 */
final class AiAssistantRetrievalService
{
    public function __construct(private readonly AiAssistantDocumentChunkRepository $chunkRepository)
    {
    }

    /**
     * @param float[] $questionEmbedding
     *
     * @return AiAssistantDocumentChunk[] top-K chunks les plus pertinents, triés par pertinence décroissante
     */
    public function findRelevantChunks(array $questionEmbedding, int $topK = 6): array
    {
        $chunks = $this->chunkRepository->findAllPublic();
        if ([] === $chunks) {
            return [];
        }

        $scored = [];
        foreach ($chunks as $chunk) {
            $scored[] = ['chunk' => $chunk, 'score' => self::cosineSimilarity($questionEmbedding, $chunk->getEmbedding())];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(
            static fn (array $entry): AiAssistantDocumentChunk => $entry['chunk'],
            \array_slice($scored, 0, $topK),
        );
    }

    /**
     * @param float[] $a
     * @param float[] $b
     */
    private static function cosineSimilarity(array $a, array $b): float
    {
        if ([] === $a || [] === $b || \count($a) !== \count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $valueA) {
            $valueB = $b[$i];
            $dot += $valueA * $valueB;
            $normA += $valueA ** 2;
            $normB += $valueB ** 2;
        }

        if (0.0 === $normA || 0.0 === $normB) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
