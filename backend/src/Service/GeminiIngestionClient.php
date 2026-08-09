<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Appels Gemini : résumé structuré du contenu source côté ingestion
 * asynchrone (App\Service\AiAssistantIngestionService), et embedding —
 * réutilisé aussi en synchrone côté conversationnel
 * (App\Service\AiAssistantChatProcessor) pour vectoriser la question du
 * visiteur avant le retrieval, dans le même espace vectoriel que les chunks
 * (même modèle d'embedding des deux côtés). Seul `summarize()` reste propre
 * à l'ingestion ; le raisonnement conversationnel proprement dit passe par
 * App\Service\ClaudeClient.
 *
 * Auth via header `x-goog-api-key` (jamais en query string, pour éviter la
 * fuite de la clé dans les logs d'accès/proxy).
 */
final class GeminiIngestionClient
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $embeddingModel,
        private readonly int $embeddingDimensions = 768,
    ) {
    }

    /**
     * @return array{summary: string, tokens: int}
     */
    public function summarize(string $sourceText): array
    {
        try {
            $response = $this->httpClient->request('POST', sprintf('%s/%s:generateContent', self::API_BASE, $this->model), [
                'headers' => ['x-goog-api-key' => $this->apiKey],
                'timeout' => 15,
                'json' => [
                    'systemInstruction' => [
                        'parts' => [[
                            'text' => "Tu résumes du contenu de portfolio professionnel pour alimenter un assistant IA. "
                                . 'Produis un résumé factuel, concis (5-8 phrases maximum), en français, qui ne perd '
                                . "aucune information concrète (chiffres, technologies, résultats). N'invente rien qui "
                                . "n'est pas dans le texte fourni. Réponds uniquement avec le résumé, sans préambule.",
                        ]],
                    ],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => $sourceText]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 1024,
                    ],
                ],
            ]);

            $data = $response->toArray();
            $summary = trim((string) ($data['candidates'][0]['content']['parts'][0]['text'] ?? ''));
            $tokens = (int) ($data['usageMetadata']['totalTokenCount'] ?? 0);

            if ('' === $summary) {
                throw new \RuntimeException('Réponse Gemini vide.');
            }

            return ['summary' => $summary, 'tokens' => $tokens];
        } catch (\Throwable $e) {
            $this->logger->error('GeminiIngestionClient::summarize a échoué.', ['error' => $e->getMessage()]);

            throw new \RuntimeException('Échec du résumé Gemini.', previous: $e);
        }
    }

    /**
     * @return array{vector: float[], tokens: int}
     */
    public function embed(string $text): array
    {
        try {
            $response = $this->httpClient->request('POST', sprintf('%s/%s:embedContent', self::API_BASE, $this->embeddingModel), [
                'headers' => ['x-goog-api-key' => $this->apiKey],
                'timeout' => 15,
                'json' => [
                    'content' => ['parts' => [['text' => $text]]],
                    'outputDimensionality' => $this->embeddingDimensions,
                ],
            ]);

            $data = $response->toArray();
            $vector = $data['embedding']['values'] ?? null;

            if (!is_array($vector) || [] === $vector) {
                throw new \RuntimeException('Vecteur d\'embedding vide.');
            }

            return ['vector' => array_map(floatval(...), $vector), 'tokens' => (int) round(mb_strlen($text) / 4)];
        } catch (\Throwable $e) {
            $this->logger->error('GeminiIngestionClient::embed a échoué.', ['error' => $e->getMessage()]);

            throw new \RuntimeException('Échec de l\'embedding Gemini.', previous: $e);
        }
    }
}
