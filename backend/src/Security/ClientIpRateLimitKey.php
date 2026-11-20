<?php

namespace App\Security;

/**
 * Clé de limitation de débit dérivée d'une IP cliente (audit 2026-11-20,
 * P1 #12) : une connexion IPv6 résidentielle ou un VPS reçoit en général un
 * /64 entier (2^64 adresses) — compter par adresse exacte laissait un seul
 * attaquant changer d'IP à chaque requête et contourner TOUS les limiteurs
 * par IP. On compte donc par préfixe /64.
 *
 * - IPv4 : inchangée.
 * - IPv4 encapsulée dans IPv6 (`::ffff:203.0.113.7`) : ramenée à l'IPv4,
 *   pour ne pas offrir deux compteurs distincts pour la même adresse.
 * - IPv6 : les 64 bits de poids faible sont mis à zéro ; le résultat reste
 *   une adresse IPv6 syntaxiquement valide (`2001:db8:1:2::`), utilisable
 *   partout où une IP est attendue.
 * - Valeur absente/invalide : `unknown` (un seul compteur partagé — même
 *   comportement qu'avant pour une requête sans IP).
 */
final class ClientIpRateLimitKey
{
    public static function fromIp(?string $ip): string
    {
        if (null === $ip || '' === $ip) {
            return 'unknown';
        }

        $packed = @inet_pton($ip);
        if (false === $packed) {
            return 'unknown';
        }

        if (4 === \strlen($packed)) {
            return (string) inet_ntop($packed);
        }

        // ::ffff:a.b.c.d (IPv4-mapped)
        if (str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return (string) inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8));
    }
}
