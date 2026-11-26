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
    private const LEAK_MARKERS = ['<context>', '</context>', '<catalogue>', '</catalogue>', '<fiches>', '</fiches>', '<profil>', '</profil>', 'GEMINI_API_KEY', 'ANTHROPIC_API_KEY', 'system prompt', 'system instruction'];

    /**
     * Identifiants de la configuration de sécurité réellement déployée (périmètre,
     * routage, authentification, supervision, rôles et entités internes). Le
     * propriétaire exige « zéro information de sécurité » : la consigne du prompt
     * ne suffit pas (un modèle peut désobéir, le corpus peut en contenir), donc
     * toute réponse qui en nomme un est remplacée — verrou déterministe.
     * Comparés sur le texte compacté (minuscules, lettres et chiffres seuls) pour
     * résister aux séparateurs (« Cloud-flare », « t r a e f i k », « Zero_Trust »).
     */
    private const SECURITY_CONFIG_COMPACT = ['traefik', 'cloudflare', 'lexik', 'scheb', 'netdata', 'uptimerobot', 'crowdsec', 'fail2ban', 'honeypot', 'champleurre', 'fullstrict'];

    /**
     * Termes ambigus une fois compactés (« a better stack », « monologue », « each
     * project task ») : vérifiés sur le texte, avec frontières de mot — et, pour les
     * noms d'entités internes, seulement sous leur forme de code (CamelCase).
     */
    private const SECURITY_CONFIG_PATTERNS = [
        '/\bubuntu\b/iu',
        '/\bdebian\b/iu',
        '/\bmonolog(?!ue)/iu',
        '/\bBetter[\s_-]*Stack\b/u',
        '/\bAccess\s+Zero\s*Trust\b/iu',
        // Noms de rôles internes : forme de code (ROLE_X, role-admin), jamais le
        // mot « rôle » ordinaire (« son rôle administratif », « a role user… »).
        '/\bROLE_[A-Z_]+\b/u',
        '/\brole[_-](?:user|editor|moderator|manager|admin|super[_-]?admin)\b/iu',
        '/\bSUPER[_\s-]?ADMIN\b/u',
        '/\b(?:ProjectExpense|ProjectHistory|ProjectTask|TimeEntry|SupportTicket)\b/u',
        // Versions des composants (recherche de vulnérabilités connues).
        '/\b(?:PHP|Symfony|API\s*Platform|Doctrine|MySQL|MariaDB|PostgreSQL|Next\.?js|Node(?:\.js)?|React|Ubuntu|Debian|Nginx|Apache|Redis|Docker)\s*v?\d+(?:\.\d+)*\b/iu',
    ];

    /**
     * Lettres cyrilliques et grecques visuellement identiques à une lettre latine :
     * la translittération phonétique (С → S) ne suffit pas, on replie par la forme
     * (у → u : aucun terme surveillé ne contient de « y », « Ubуntu » doit tomber).
     */
    private const CONFUSABLES = [
        'а' => 'a', 'в' => 'b', 'с' => 'c', 'е' => 'e', 'һ' => 'h', 'і' => 'i', 'ј' => 'j', 'к' => 'k', 'м' => 'm', 'н' => 'h', 'о' => 'o', 'р' => 'p', 'ѕ' => 's', 'т' => 't', 'у' => 'u', 'х' => 'x', 'ԁ' => 'd', 'ӏ' => 'l',
        'А' => 'A', 'В' => 'B', 'С' => 'C', 'Е' => 'E', 'Н' => 'H', 'І' => 'I', 'Ј' => 'J', 'К' => 'K', 'М' => 'M', 'О' => 'O', 'Р' => 'P', 'Ѕ' => 'S', 'Т' => 'T', 'У' => 'U', 'Х' => 'X',
        'α' => 'a', 'ο' => 'o', 'ι' => 'i', 'κ' => 'k', 'ν' => 'v', 'ρ' => 'p', 'τ' => 't', 'υ' => 'u', 'χ' => 'x', 'ε' => 'e',
        'Α' => 'A', 'Β' => 'B', 'Ε' => 'E', 'Η' => 'H', 'Ι' => 'I', 'Κ' => 'K', 'Μ' => 'M', 'Ν' => 'N', 'Ο' => 'O', 'Ρ' => 'P', 'Τ' => 'T', 'Υ' => 'Y', 'Χ' => 'X', 'Ζ' => 'Z',
    ];

    /** Hiérarchie de rôles recopiée en clair (« USER, EDITOR, MODERATOR… ») : 3 jetons distincts ou plus. */
    private const ROLE_TOKENS = '/\b(USER|EDITOR|MODERATOR|MANAGER|ADMIN)\b/u';

    /**
     * @return array{text: string, leaked: bool, confidential: bool}
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

        return ['text' => $text, 'leaked' => $leaked, 'confidential' => $this->revealsSecurityConfig($rawAnswer)];
    }

    /** Refus fixe qui remplace une réponse nommant la configuration de sécurité. */
    public function confidentialRefusal(string $locale): string
    {
        return 'en' === $locale
            ? 'Security configuration details for Horlynat\'s systems are not published. To talk about his security approach, [Send a message](/contact?mode=direct).'
            : 'Les détails de configuration de sécurité des systèmes de Horlynat ne sont pas publiés. Pour échanger sur son approche de la sécurité, [Envoyer un message](/contact?mode=direct).';
    }

    /** Vrai si le texte nomme un élément de la configuration de sécurité déployée. */
    public function revealsSecurityConfig(string $text): bool
    {
        // Lettres et chiffres seuls, après décomposition des accents et des formes de
        // compatibilité (pleine chasse, ligatures) : « Ｔｒａｅｆｉｋ », « Tr-æfik »… Les
        // marques diacritiques détachées (\p{Mn}) tombent avec le reste.
        // Homoglyphes cyrilliques/grecs (« Сloudflare », « Trаefik ») repliés vers
        // l'ASCII avant toute comparaison.
        $latin = \Transliterator::create('Any-Latin; Latin-ASCII')?->transliterate(strtr($text, self::CONFUSABLES));
        $decomposed = \Normalizer::normalize(false === $latin || null === $latin ? $text : $latin, \Normalizer::FORM_KD);
        $decomposed = false === $decomposed ? $text : $decomposed;
        $compact = (string) preg_replace('/[^\p{Ll}\p{Nd}]+/u', '', mb_strtolower($decomposed));
        foreach (self::SECURITY_CONFIG_COMPACT as $term) {
            if (str_contains($compact, $term)) {
                return true;
            }
        }
        $plain = (string) preg_replace('/\p{Mn}+/u', '', $decomposed);
        foreach (self::SECURITY_CONFIG_PATTERNS as $pattern) {
            if (1 === preg_match($pattern, $plain)) {
                return true;
            }
        }
        if (preg_match_all(self::ROLE_TOKENS, $plain, $m) > 0 && \count(array_unique($m[1])) >= 3) {
            return true;
        }

        return false;
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

            if ($isLeaked || $this->revealsSecurityConfig($text)) {
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
