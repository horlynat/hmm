<?php

namespace App\Service;

/**
 * Masque les secrets susceptibles d'apparaître dans un message d'exception
 * avant qu'il ne parte dans les logs (stderr JSON + fichier lu par fail2ban,
 * cf. config/packages/monolog.yaml) ou dans une alerte email
 * (App\Service\ErrorNotifier).
 *
 * Cas concret : une PDOException / DBALException expose la DSN complète
 * (« ...mysql://app:S3cr3t@db:3306/app... ») dans son message ; un échec
 * d'appel HTTP peut recracher un en-tête Authorization. Rien de tout ça n'a
 * sa place dans un journal.
 */
final class SensitiveDataScrubber
{
    private const REDACTION = '***';

    /**
     * Paires [motif PCRE, remplacement]. Le remplacement conserve les groupes
     * capturants nécessaires ($1…) et masque la partie sensible.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const RULES = [
        // Jeton Bearer (traité avant la règle "authorization:" générique).
        ['#(\bBearer\s+)[A-Za-z0-9._~+/\-]{6,}=*#i', '$1'.self::REDACTION],
        // userinfo d'une URL/DSN : scheme://[user]:pass@host -> scheme://[user]:***@host
        ['#([a-z][a-z0-9+.\-]*://[^/\s:@]*:)[^/\s@]+(@)#i', '$1'.self::REDACTION.'$2'],
        // En-tête / clé Authorization : masque toute la valeur jusqu'au séparateur.
        ['#(authorization["\']?\s*[:=]\s*)["\']?[^"\'\r\n,;}]+#i', '$1'.self::REDACTION],
        // password=... / secret: "..." / api_key=... / token => ...
        ['#((?:password|passwd|pwd|secret|api[_-]?key|access[_-]?key|secret[_-]?key|token|passphrase)["\']?\s*(?:=>|[:=])\s*["\']?)[^"\'\s,;}) ]+#i', '$1'.self::REDACTION],
    ];

    public function scrub(string $text): string
    {
        foreach (self::RULES as [$pattern, $replacement]) {
            $text = (string) preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }
}
