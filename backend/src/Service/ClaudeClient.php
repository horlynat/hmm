<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Appel à l'API Claude (Anthropic Messages) pour le pipeline conversationnel
 * temps réel de l'assistant IA. Séparation stricte system/user via la
 * structure native de l'API (jamais un prompt unique concaténé) — mesure
 * anti prompt-injection documentée en §4.5 du document d'architecture.
 *
 * Bascule automatique vers le modèle de repli (Haiku) si le modèle principal
 * échoue (surcharge, quota, erreur 5xx) — cf. §4.7 "Panne fournisseur IA".
 *
 * Cache de prompt (`cache_control`) sur le system prompt : celui-ci est
 * identique à chaque question tant que le contenu n'a pas été réingéré
 * (même contexte RAG, mêmes règles) — coûteux à renvoyer en entier à chaque
 * appel. TTL 1h plutôt que le défaut 5 min : sur un portfolio, le trafic est
 * épars, un TTL court manquerait le cache la plupart du temps malgré un
 * write plus cher (2x le prix d'entrée au lieu de 1,25x) ; les lectures en
 * cache ne coûtent que 10% du prix d'entrée normal, largement rentable dès
 * qu'une deuxième question arrive dans l'heure.
 */
final class ClaudeClient
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $fallbackModel,
    ) {
    }

    /**
     * $useSonnet vient de App\Service\AiAssistantModelSelector : Sonnet
     * (CLAUDE_MODEL) pour les échanges complexes/commerciaux, Haiku
     * (CLAUDE_MODEL_FALLBACK) pour les questions simples type FAQ — ce même
     * modèle Haiku sert aussi de repli de résilience si l'autre échoue
     * (surcharge, quota, 5xx), dans les deux sens.
     *
     * @param array<int, array{role: string, text: string}> $history
     *
     * @return array{text: string, model: string, inputTokens: int, outputTokens: int, cacheCreationTokens: int, cacheReadTokens: int}
     */
    public function ask(string $systemPrompt, array $history, string $question, bool $useSonnet, int $maxTokens = 1024): array
    {
        $messages = [];
        foreach ($history as $turn) {
            $messages[] = ['role' => 'assistant' === $turn['role'] ? 'assistant' : 'user', 'content' => $turn['text']];
        }
        $messages[] = ['role' => 'user', 'content' => $question];

        $primary = $useSonnet ? $this->model : $this->fallbackModel;
        $secondary = $useSonnet ? $this->fallbackModel : $this->model;

        try {
            return $this->call($primary, $systemPrompt, $messages, $maxTokens);
        } catch (\Throwable $primaryError) {
            $this->logger->warning('ClaudeClient : modèle choisi indisponible, repli sur l\'autre modèle.', [
                'model' => $primary,
                'error' => $primaryError->getMessage(),
            ]);

            try {
                return $this->call($secondary, $systemPrompt, $messages, $maxTokens);
            } catch (\Throwable $fallbackError) {
                $this->logger->error('ClaudeClient : les deux modèles sont indisponibles.', [
                    'secondary' => $secondary,
                    'error' => $fallbackError->getMessage(),
                ]);

                throw new \RuntimeException('Assistant IA indisponible (Claude injoignable).', previous: $fallbackError);
            }
        }
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     *
     * @return array{text: string, model: string, inputTokens: int, outputTokens: int, cacheCreationTokens: int, cacheReadTokens: int}
     */
    private function call(string $model, string $systemPrompt, array $messages, int $maxTokens): array
    {
        $response = $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type' => 'application/json',
            ],
            // Idle timeout du client HTTP — la requête n'étant pas streamée, ça
            // couvre en pratique toute la durée de génération. Mesuré en direct
            // contre l'API : une réponse Sonnet proche de max_tokens (1024) sur
            // une question commerciale prend jusqu'à ~14s. 12s coupait la
            // connexion avant la fin — le pire scénario côté budget : Anthropic
            // termine probablement la génération malgré l'abandon côté client
            // (et la facture potentiellement), puis le repli déclenche un
            // second appel payant sur l'autre modèle. 30s laisse une marge
            // confortable au lieu de risquer une double facturation invisible.
            'timeout' => 30,
            'json' => [
                'model' => $model,
                'max_tokens' => $maxTokens,
                // Désactivé explicitement : le raisonnement étendu se déclenche
                // parfois de façon non déterministe côté Sonnet (constaté en
                // test direct, sans qu'on l'ait demandé) et consomme une partie
                // du budget max_tokens sur du contenu jamais montré au visiteur
                // — facturé au tarif de sortie comme le texte visible, donc
                // du coût pur sans bénéfice pour un assistant censé "rester
                // bref, professionnel et factuel" (cf. system prompt). Sans
                // rapport avec la sélection du modèle (Sonnet/Haiku) via
                // AiAssistantModelSelector — ça concerne le raisonnement interne
                // d'un appel déjà choisi, pas lequel des deux modèles appeler.
                'thinking' => ['type' => 'disabled'],
                // Bloc unique avec cache_control : voir le commentaire de classe.
                'system' => [[
                    'type' => 'text',
                    'text' => $systemPrompt,
                    'cache_control' => ['type' => 'ephemeral', 'ttl' => '1h'],
                ]],
                'messages' => $messages,
            ],
        ]);

        $data = $response->toArray();
        // Ne pas supposer que le texte est en content[0] : un bloc "thinking"
        // peut le précéder (cf. commentaire sur `thinking` ci-dessus — en
        // pratique ça ne devrait plus arriver une fois désactivé, mais autant
        // rester robuste plutôt que de déclencher un repli inutile, et donc un
        // second appel payant, sur une réponse qui existe bel et bien).
        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if ('text' === ($block['type'] ?? null)) {
                $text = trim((string) ($block['text'] ?? ''));
                break;
            }
        }

        if ('' === $text) {
            throw new \RuntimeException(sprintf('Réponse Claude vide (modèle %s).', $model));
        }

        return [
            'text' => $text,
            'model' => $model,
            'inputTokens' => (int) ($data['usage']['input_tokens'] ?? 0),
            'outputTokens' => (int) ($data['usage']['output_tokens'] ?? 0),
            'cacheCreationTokens' => (int) ($data['usage']['cache_creation_input_tokens'] ?? 0),
            'cacheReadTokens' => (int) ($data['usage']['cache_read_input_tokens'] ?? 0),
        ];
    }
}
