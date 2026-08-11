<?php

namespace App\Service;

/**
 * Nettoyage de la réponse Claude avant retour au client — plafonnement de
 * longueur, détection de fuite du system prompt/des balises de délimitation
 * du contexte RAG, liste blanche de domaines dans les liens markdown (cf.
 * §4.5 du document d'architecture assistant IA).
 */
final class AiAssistantOutputSanitizer
{
    private const MAX_LENGTH = 2000;

    private const ALLOWED_LINK_DOMAINS = ['horlynat.com', 'github.com', 'linkedin.com'];

    /** Fragments qui ne devraient jamais apparaître dans une réponse saine — signe d'une fuite du system prompt/du contexte délimité. */
    private const LEAK_MARKERS = ['<context>', '</context>', 'GEMINI_API_KEY', 'ANTHROPIC_API_KEY', 'system prompt', 'system instruction'];

    /**
     * @return array{text: string, leaked: bool}
     */
    public function sanitize(string $rawAnswer): array
    {
        $leaked = false;

        foreach (self::LEAK_MARKERS as $marker) {
            if (false !== stripos($rawAnswer, $marker)) {
                $leaked = true;
                break;
            }
        }

        $text = mb_substr(trim($rawAnswer), 0, self::MAX_LENGTH);
        $text = $this->stripDisallowedLinks($text);

        return ['text' => $text, 'leaked' => $leaked];
    }

    /**
     * Nettoie les suggestions de question de suivi générées par Claude après le
     * délimiteur `###SUGGESTIONS###` (cf. AiAssistantChatProcessor::buildSystemPrompt) :
     * retire la puce, coupe la longueur (ce sont des libellés de bouton, pas des
     * réponses), retire tout lien markdown, et applique la même détection de fuite
     * que sanitize() — Claude pourrait tout aussi bien fuiter dans une suggestion.
     *
     * @param string[] $rawLines
     *
     * @return string[] au plus 3 suggestions
     */
    public function sanitizeSuggestions(array $rawLines): array
    {
        $suggestions = [];

        foreach ($rawLines as $line) {
            $text = trim(ltrim(trim($line), "-•*\t "));

            if ('' === $text) {
                continue;
            }

            $isLeaked = false;
            foreach (self::LEAK_MARKERS as $marker) {
                if (false !== stripos($text, $marker)) {
                    $isLeaked = true;
                    break;
                }
            }

            if ($isLeaked) {
                continue;
            }

            $text = (string) preg_replace('/\[([^\]]+)\]\(https?:\/\/[^)]+\)/i', '$1', $text);
            $suggestions[] = mb_substr($text, 0, 120);

            if (3 === \count($suggestions)) {
                break;
            }
        }

        return $suggestions;
    }

    private function stripDisallowedLinks(string $text): string
    {
        return (string) preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/i',
            function (array $matches): string {
                $host = parse_url($matches[2], PHP_URL_HOST) ?: '';
                foreach (self::ALLOWED_LINK_DOMAINS as $allowed) {
                    if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                        return $matches[0];
                    }
                }

                // Domaine hors liste blanche : on garde le libellé, on retire le lien.
                return $matches[1];
            },
            $text,
        );
    }
}
