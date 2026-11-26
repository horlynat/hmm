<?php

namespace App\Service;

/**
 * Nettoyage de la réponse Claude avant retour au client — plafonnement de
 * longueur, détection de fuite du system prompt/des balises de délimitation
 * du contexte RAG, liste blanche d'hôtes dans les liens markdown (cf. §4.5 du
 * document d'architecture assistant IA), et verrous « zéro information de
 * sécurité » (exigence du propriétaire, 2026-11-26) ; le refus « client » est
 * imposé par la consigne seule, aucun verrou sur les noms (7e contre-audit).
 *
 * Principe anti-oracle (3e contre-audit) : un refus ne doit jamais permettre
 * de déduire ce qui est réellement déployé ou qui est réellement client. Les
 * refus sont donc génériques, identiques à ce que la consigne impose pour TOUT
 * outil ou TOUT nom (utilisé ou non), toujours sans suggestions, et la liste
 * surveillée mêle composants réels et produits courants non utilisés.
 */
final class AiAssistantOutputSanitizer
{
    private const MAX_LENGTH = 2000;

    /** Hôtes publics autorisés dans un lien (jamais un sous-domaine interne). */
    private const ALLOWED_LINK_HOSTS = ['horlynat.com', 'www.horlynat.com', 'github.com', 'www.github.com', 'linkedin.com', 'www.linkedin.com'];

    /** Fragments qui ne devraient jamais apparaître dans une réponse saine — signe d'une fuite du system prompt/du contexte délimité. */
    private const LEAK_MARKERS = ['<context>', '</context>', '<catalogue>', '</catalogue>', '<fiches>', '</fiches>', '<profil>', '</profil>', 'GEMINI_API_KEY', 'ANTHROPIC_API_KEY', 'system prompt', 'system instruction'];

    /**
     * Produits et fournisseurs d'infrastructure, de sécurité, d'authentification,
     * de supervision et d'hébergement. La liste MÊLE volontairement des composants
     * réellement déployés et des produits courants qui ne le sont pas : le refus
     * étant identique pour tous, il ne révèle rien (anti-oracle). Ordre
     * alphabétique, sans distinction. Comparés sur le texte compacté (minuscules,
     * lettres et chiffres seuls) pour résister aux séparateurs et homoglyphes.
     */
    private const SECURITY_CONFIG_COMPACT = [
        'akamai', 'alpinelinux', 'apparmor', 'auth0', 'certbot', 'champleurre', 'cloudflare', 'contabo', 'crowdstrike',
        'darkhorlynat', 'datadog', 'debian', 'digitalocean', 'fullstrict', 'grafana', 'haproxy', 'hetzner', 'imperva',
        'keycloak', 'linode', 'modsecurity', 'nelmio', 'ntfy', 'ovhcloud', 'rockylinux', 'scaleway', 'sucuri', 'traefik',
        'ubuntu', 'unattendedupgrades', 'uptimekuma', 'uptimerobot', 'vultr', 'wazuh', 'zabbix',
    ];

    /** Mots courants qui contiennent un terme surveillé une fois compactés : neutralisés avant comparaison. */
    private const COMPACT_SAFE_WORDS = ['monologue'];

    /**
     * Termes ambigus une fois compactés : frontières de mot exigées (« Internet
     * data », « crowd security », « a better stack »), entités internes sous leur
     * seule forme de code (CamelCase).
     */
    private const SECURITY_CONFIG_PATTERNS = [
        // Termes qui naissent aussi du collage de deux mots courants (« audit de »,
        // « also caters », « a recent OS », « children some », « high criticality ») :
        // en mot entier seulement.
        '/\b(?:adminer|auditd|caddy|centos|drenso|fastly|ghcr|lexik|pingdom|prometheus|scheb|socat)\b/iu',
        '/\bmonolog(?!ue)/iu',
        '/\blet\W?s\W*encrypt\b/iu',
        '/\bnetdata\b/iu',
        '/\bcrowdsec\b/iu',
        '/\bfail2ban\b/iu',
        '/\bufw\b/iu',
        '/\bnginx\b/iu',
        '/\bokta\b/iu',
        '/\bsentry\b/iu',
        // « Better Stack » (le service) ou « betterstack », jamais « a better stack ».
        '/\bBetter[\s_-]*Stack\b/u',
        '/\bbetter[_-]?stack\b(?<!better stack)/iu',
        // Noms de rôles internes : forme de code (ROLE_X, role-admin), jamais le
        // mot « rôle » ordinaire (« son rôle administratif », « a role user… »).
        '/\bROLE_[A-Z_]+\b/u',
        '/\brole[_-](?:user|editor|moderator|manager|admin|super[_-]?admin)\b/iu',
        '/\bsuper[_\s-]?admin\b/iu',
        '/\b(?:ProjectExpense|ProjectHistory|ProjectTask|TimeEntry|SupportTicket)\b/u',
        // Versions de composants (recherche de vulnérabilités connues) : seulement
        // une forme de version — « 8.4 », « v2 », « version 8 » — jamais un nombre
        // seul, qui compte presque toujours autre chose (« PHP 8 ans », « Docker
        // (2024) », « React 3 projets », « 9.5/10 »).
        '/\b(?:PHP|Symfony|API\s*Platform|Doctrine|MySQL|MariaDB|PostgreSQL|Next\.?js|Node(?:\.js)?|React|Redis|Docker(?:\s*Compose)?)[\s(@:_,-]*(?:(?:en\s+)?version\s*\d+(?:\.\d+)*|v\.?\s*\d+(?:\.\d+)*|\d+\.\d+(?:\.\d+)*(?!\d|\s*(?:\/|ans?\b|years?\b|mois\b|months?\b)))/iu',
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

    /** Abréviations dont le point ne termine pas la phrase (découpage des résumés). */
    private const ABBREVIATIONS = ['ex.', 'cf.', 'M.', 'Mme.', 'e.g.', 'i.e.', 'p.', 'n°.', 'vs.'];

    /** Refus générique « outil / produit » : la consigne impose exactement cette phrase. */
    public const PRODUCT_REFUSAL = [
        'fr' => 'Je ne détaille pas les outils, produits ou fournisseurs précis des systèmes en production, qu\'ils soient utilisés ou non. Pour échanger sur l\'approche de Horlynat en matière de sécurité, [Envoyer un message](/contact?mode=direct).',
        'en' => 'I do not go into the specific tools, products or providers of production systems, whether they are used or not. To discuss Horlynat\'s approach to security, [Send a message](/contact?mode=direct).',
    ];

    /** Refus « client » : la consigne impose exactement cette phrase, sans répéter le nom demandé. */
    public const CLIENT_REFUSAL = [
        'fr' => 'Les clients de Horlynat sont confidentiels : je ne confirme ni n\'infirme aucun nom. Pour échanger sur ses réalisations, [Envoyer un message](/contact?mode=direct).',
        'en' => 'Horlynat\'s clients are confidential: I neither confirm nor deny any name. To discuss his projects, [Send a message](/contact?mode=direct).',
    ];

    private static ?\Transliterator $transliterator = null;

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

    /** Refus qui remplace une réponse nommant un outil ou un produit surveillé. */
    public function confidentialRefusal(string $locale): string
    {
        return self::PRODUCT_REFUSAL['en' === $locale ? 'en' : 'fr'];
    }

    /** Refus « client » imposé mot pour mot par la consigne (aucun verrou sur les noms). */
    public function clientRefusal(string $locale): string
    {
        return self::CLIENT_REFUSAL['en' === $locale ? 'en' : 'fr'];
    }

    /**
     * Vrai si la réponse est l'un des refus imposés (produit ou client), qu'elle
     * vienne du modèle (consigne suivie) ou, pour le refus « produit », du verrou : l'appelant retire alors
     * les suggestions dans les deux cas, pour que rien ne les distingue.
     */
    public function isFixedRefusal(string $answer): bool
    {
        return null !== $this->canonicalRefusal($answer);
    }

    /**
     * Le refus imposé (texte canonique) que la réponse reprend, même avec une
     * autre typographie (apostrophe courbe, gras, espaces) — null sinon.
     * L'appelant remplace la réponse par ce texte : les deux chemins (modèle
     * obéissant, verrou) produisent alors une sortie strictement identique.
     */
    public function canonicalRefusal(string $answer): ?string
    {
        $normalize = static fn (string $t): string => ltrim((string) preg_replace('/\s+/u', ' ', trim(str_replace(['’', '‘', '*', '_', '«', '»', '"', '“', '”'], ["'", "'", '', '', '', '', '', '', ''], $t))), " :-–—.");
        $normalized = $normalize($answer);
        foreach ([...array_values(self::PRODUCT_REFUSAL), ...array_values(self::CLIENT_REFUSAL)] as $refusal) {
            $firstSentence = $normalize((string) preg_split('/[.:]/u', $refusal, 2)[0]);
            if (str_starts_with($normalized, $firstSentence)) {
                return $refusal;
            }
        }

        return null;
    }

    /**
     * Retire d'un texte (source ou résumé envoyé à une IA) chaque phrase — ou
     * ligne, ou proposition séparée par « ; » — qui nomme la configuration de
     * sécurité, et garde les autres telles quelles, séparateurs compris.
     */
    public function withoutSecurityConfig(string $text): string
    {
        $protected = str_replace(self::ABBREVIATIONS, array_map(static fn (string $a): string => str_replace('.', "\u{E000}", $a), self::ABBREVIATIONS), $text);
        $parts = preg_split('/((?<=[.!?…;])[ \t]+|\R+)/u', $protected, -1, \PREG_SPLIT_DELIM_CAPTURE) ?: [$protected];

        $out = '';
        $dropNextSeparator = false;
        foreach ($parts as $i => $part) {
            if (1 === $i % 2) { // séparateur capturé
                if (!$dropNextSeparator) {
                    $out .= $part;
                }
                continue;
            }
            $restored = str_replace("\u{E000}", '.', $part);
            $dropNextSeparator = '' !== trim($restored) && $this->revealsSecurityConfig($restored);
            if (!$dropNextSeparator) {
                $out .= $restored;
            }
        }

        return trim((string) preg_replace('/[ \t]+(\R)/u', '$1', $out));
    }

    /** Vrai si le texte nomme un élément surveillé (outil, produit, rôle, entité interne, version). */
    public function revealsSecurityConfig(string $text): bool
    {
        $decomposed = $this->decompose($text);
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
     * $filterSecurityConfig = false pour les questions de qualification d'un devis,
     * qui portent légitimement sur le système du prospect (« sous PHP 7.4 ? »).
     *
     * @param string[] $rawLines
     *
     * @return string[] au plus 3 suggestions
     */
    public function sanitizeSuggestions(array $rawLines, bool $filterSecurityConfig = true): array
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

            if ($isLeaked || ($filterSecurityConfig && $this->revealsSecurityConfig($text))) {
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

    private function decompose(string $text): string
    {
        // Homoglyphes cyrilliques/grecs repliés par la forme, puis ASCII, puis
        // décomposition (pleine chasse, ligatures, accents détachés).
        self::$transliterator ??= \Transliterator::create('Any-Latin; Latin-ASCII');
        $latin = self::$transliterator?->transliterate(strtr($text, self::CONFUSABLES));
        $decomposed = \Normalizer::normalize(false === $latin || null === $latin ? $text : $latin, \Normalizer::FORM_KD);

        return false === $decomposed ? $text : $decomposed;
    }

    private function stripDisallowedLinks(string $text): string
    {
        return (string) preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/i',
            static function (array $matches): string {
                $host = strtolower((string) (parse_url($matches[2], PHP_URL_HOST) ?: ''));

                // Hôte hors liste blanche (y compris un sous-domaine interne) :
                // on garde le libellé, on retire le lien.
                return \in_array($host, self::ALLOWED_LINK_HOSTS, true) ? $matches[0] : $matches[1];
            },
            $text,
        );
    }

}
