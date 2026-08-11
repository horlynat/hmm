<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Appel Gemini : résumé structuré du contenu source côté ingestion asynchrone
 * (App\Service\AiAssistantIngestionService) — le raisonnement conversationnel
 * proprement dit passe par App\Service\ClaudeClient, à qui le corpus complet
 * est envoyé tel quel (pas de retrieval par embedding, cf. docblock de
 * App\State\AiAssistantChatProcessor).
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
}
