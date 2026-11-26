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
    private const SECURITY_CONFIG_COMPACT = [
        'traefik', 'cloudflare', 'lexik', 'scheb', 'uptimerobot', 'ubuntu', 'debian', 'monolog',
        'champleurre', 'fullstrict', 'accesszerotrust', 'zerotrustaccess',
        // Composants réellement déployés (composer.json, infra) — contre-audit 5dd6646.
        'adminer', 'ntfy', 'nelmio', 'drenso', 'socat',
    ];

    /** Mots courants qui contiennent un terme surveillé une fois compactés : neutralisés avant comparaison. */
    private const COMPACT_SAFE_WORDS = ['monologue'];

    /**
     * Termes ambigus une fois compactés (« a better stack », « monologue », « each
     * project task ») : vérifiés sur le texte, avec frontières de mot — et, pour les
     * noms d'entités internes, seulement sous leur forme de code (CamelCase).
     */
    private const SECURITY_CONFIG_PATTERNS = [
        // Mots qui existent aussi dans la langue courante une fois compactés
        // (« Internet data », « crowd security ») : frontières de mot exigées.
        '/\bnetdata\b/iu',
        '/\bcrowdsec\b/iu',
        '/\bfail2ban\b/iu',
        // « Better Stack » (le service) ou « betterstack », jamais « a better stack ».
        '/\bBetter[\s_-]*Stack\b/u',
        '/\bbetter[_-]?stack\b(?<!better stack)/iu',
        // Noms de rôles internes : forme de code (ROLE_X, role-admin), jamais le
        // mot « rôle » ordinaire (« son rôle administratif », « a role user… »).
        '/\bROLE_[A-Z_]+\b/u',
        '/\brole[_-](?:user|editor|moderator|manager|admin|super[_-]?admin)\b/iu',
        '/\bsuper[_\s-]?admin\b/iu',
        '/\b(?:ProjectExpense|ProjectHistory|ProjectTask|TimeEntry|SupportTicket)\b/u',
        // Versions des composants (recherche de vulnérabilités connues) : « PHP 8.4 »,
        // « Symfony (8.1) », « Next.js@16 », « PHP version 8 », « v2 »… mais jamais un
        // nombre qui compte autre chose (« PHP 8 ans », « React 3 projets », « 9/10 »).
        '/\b(?:PHP|Symfony|API\s*Platform|Doctrine|MySQL|MariaDB|PostgreSQL|Next\.?js|Node(?:\.js)?|React|Nginx|Redis|Docker(?:\s*Compose)?)[\s(@:_,-]*(?:(?:en\s+)?version\s*|v\.?\s*)?(?:\d+\.\d+(?:\.\d+)*|v\d+|\d+(?!\d|[.,]\d|\/|\s*(?:ans?|années?|years?|jours?|days?|mois|months?|semaines?|weeks?|projets?|projects?|pages?|fois|times|FCFA|XAF|%|sur|out)\b))/iu',
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

    /**
     * Hiérarchie de rôles recopiée en liste (« USER, EDITOR et MODERATOR »,
     * « user / admin / manager ») : 3 jetons distincts ou plus, enchaînés.
     */
    private const ROLE_LIST = '/\b(?:user|editor|moderator|manager|admin)s?\b(?:\s*(?:,|\/|;|\bet\b|\band\b|\bou\b|\bor\b)\s*\b(?:user|editor|moderator|manager|admin)s?\b){2,}/iu';

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

    /**
     * Retire d'un texte (résumé ingéré) chaque phrase qui nomme la configuration
     * de sécurité, et garde les autres telles quelles.
     */
    public function withoutSecurityConfig(string $text): string
    {
        $sentences = preg_split('/(?<=[.!?…])\s+/u', trim($text)) ?: [$text];
        $kept = array_filter($sentences, fn (string $s): bool => !$this->revealsSecurityConfig($s));

        return implode(' ', $kept);
    }

    /** Refus fixe qui remplace une réponse citant le nom d'un client. */
    public function clientRefusal(string $locale): string
    {
        return 'en' === $locale
            ? 'The names of Horlynat\'s clients are confidential. To discuss his projects, [Send a message](/contact?mode=direct).'
            : 'Le nom des clients de Horlynat est confidentiel. Pour échanger sur ses réalisations, [Envoyer un message](/contact?mode=direct).';
    }

    /**
     * Vrai si le texte cite l'un des noms donnés (comparaison compactée, mêmes
     * replis que le verrou de sécurité : séparateurs, accents, homoglyphes).
     * Les noms de moins de 5 lettres sont ignorés (faux positifs certains).
     *
     * @param list<string> $names
     */
    public function mentionsAnyName(string $text, array $names): bool
    {
        $compactText = $this->compact($text);
        foreach ($names as $name) {
            $n = $this->compact($name);
            if (\strlen($n) >= 5 && str_contains($compactText, $n)) {
                return true;
            }
        }

        return false;
    }

    private function compact(string $text): string
    {
        $latin = \Transliterator::create('Any-Latin; Latin-ASCII')?->transliterate(strtr($text, self::CONFUSABLES));
        $decomposed = \Normalizer::normalize(false === $latin || null === $latin ? $text : $latin, \Normalizer::FORM_KD);

        return (string) preg_replace('/[^\p{Ll}\p{Nd}]+/u', '', mb_strtolower(false === $decomposed ? $text : $decomposed));
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
        $lower = str_replace(self::COMPACT_SAFE_WORDS, ' ', mb_strtolower($decomposed));
        $compact = (string) preg_replace('/[^\p{Ll}\p{Nd}]+/u', '', $lower);
        foreach (self::SECURITY_CONFIG_COMPACT as $term) {
            if (str_contains($compact, $term)) {
                return true;
            }
        }
        // Marques diacritiques et caractères invisibles (espace de largeur nulle,
        // trait d'union conditionnel, liant…) retirés avant les motifs.
        $plain = (string) preg_replace('/[\p{Mn}\p{Cf}]+/u', '', $decomposed);
        foreach (self::SECURITY_CONFIG_PATTERNS as $pattern) {
            if (1 === preg_match($pattern, $plain)) {
                return true;
            }
        }
        if (preg_match_all(self::ROLE_LIST, $plain, $lists) > 0) {
            foreach ($lists[0] as $list) {
                preg_match_all('/\b(user|editor|moderator|manager|admin)s?\b/iu', $list, $tokens);
                if (\count(array_unique(array_map('strtolower', $tokens[1]))) >= 3) {
                    return true;
                }
            }
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
