<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Traduction fr <-> en en direct (un champ à la fois, pendant la frappe dans
 * le back-office — cf. assets/controllers/bilingual_field_controller.js) via
 * Gemini. Distinct de App\Service\GeminiIngestionClient (résumé du corpus
 * pour le RAG de l'assistant IA, appelé en tâche de fond) : usage
 * interactif, donc temperature basse et sortie courte non structurée
 * (le texte traduit, rien d'autre).
 *
 * Auth via header `x-goog-api-key`, jamais en query string (même politique
 * que GeminiIngestionClient — évite la fuite de la clé dans les logs
 * d'accès/proxy).
 */
final class GeminiTranslator
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

    private const LOCALE_NAMES = ['fr' => 'français', 'en' => 'anglais'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    /**
     * @throws \RuntimeException si l'appel échoue ou renvoie une réponse vide
     */
    public function translate(string $text, string $sourceLocale, string $targetLocale): string
    {
        $text = trim($text);
        if ('' === $text) {
            return '';
        }

        $sourceName = self::LOCALE_NAMES[$sourceLocale] ?? $sourceLocale;
        $targetName = self::LOCALE_NAMES[$targetLocale] ?? $targetLocale;

        try {
            $response = $this->httpClient->request('POST', sprintf('%s/%s:generateContent', self::API_BASE, $this->model), [
                'headers' => ['x-goog-api-key' => $this->apiKey],
                'timeout' => 10,
                'json' => [
                    'systemInstruction' => [
                        'parts' => [[
                            'text' => "Tu traduis un unique champ de contenu éditorial du {$sourceName} vers ".
                                "l'{$targetName}, pour le site professionnel d'un développeur full-stack (aussi ".
                                'consultant cybersécurité et technicien assurance) basé à Brazzaville. Ton direct '.
                                'et professionnel. Ne reformule pas, ne raccourcis pas, ne développe pas : une '.
                                'traduction fidèle. Conserve la mise en forme (retours à la ligne). Réponds '.
                                'UNIQUEMENT avec le texte traduit, sans guillemets, sans préambule, sans note.',
                        ]],
                    ],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => $text]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'maxOutputTokens' => 2048,
                    ],
                ],
            ]);

            $data = $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->warning('GeminiTranslator : appel échoué.', ['error' => $e->getMessage()]);

            throw new \RuntimeException('Traduction indisponible.', previous: $e);
        }

        $translated = trim((string) ($data['candidates'][0]['content']['parts'][0]['text'] ?? ''));
        if ('' === $translated) {
            throw new \RuntimeException('Réponse de traduction vide.');
        }

        return $translated;
    }
}
