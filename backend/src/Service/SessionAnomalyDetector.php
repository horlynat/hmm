<?php

namespace App\Service;

/**
 * Détection d'anomalies sur les sessions actives d'un même compte :
 *
 * - "voyage impossible" : deux sessions actives dont la distance entre les
 *   localisations implique une vitesse de déplacement physiquement
 *   impossible dans l'intervalle de temps écoulé entre les deux (plus
 *   rapide qu'un vol commercial) — signe probable d'identifiants partagés
 *   ou compromis, pas d'un utilisateur qui voyage réellement.
 * - limite de sessions concurrentes : au-delà d'un seuil, les sessions les
 *   plus anciennes d'un compte sont désignées pour éviction automatique
 *   (appliquée par LoginListener à la connexion, via UserSessionRevoker).
 *
 * Service volontairement "pur" (aucune dépendance DB/HTTP) : reçoit des
 * données déjà résolues (coordonnées, timestamps) en entrée et ne fait
 * aucun appel externe lui-même — facilite les tests unitaires et découple la
 * détection de la façon dont les coordonnées sont obtenues (cache HTTP,
 * IP locale sans localisation, etc., gérés par l'appelant).
 */
final class SessionAnomalyDetector
{
    /**
     * km/h — plus rapide qu'aucune ligne commerciale actuelle (~900-1000 km/h
     * en croisière). Au-delà, aucun déplacement physique légitime ne l'explique.
     */
    private const IMPOSSIBLE_SPEED_KMH = 900.0;

    /**
     * Sous ce délai entre deux connexions, on ignore le calcul de vitesse : le
     * bruit de géolocalisation IP (résolution ville, pas GPS) produirait des
     * faux positifs sur deux requêtes quasi simultanées depuis le même endroit.
     */
    private const MIN_INTERVAL_SECONDS = 60;

    public const DEFAULT_MAX_CONCURRENT_SESSIONS = 5;

    /**
     * @param list<array{sessionId: string, latitude: ?float, longitude: ?float, at: \DateTimeImmutable}> $sessions
     *                                                                                                     Sessions ACTIVES d'un même utilisateur uniquement — à appeler une fois par utilisateur,
     *                                                                                                     jamais en mélangeant les sessions de deux comptes différents.
     *
     * @return list<array{sessionIdA: string, sessionIdB: string, distanceKm: float, impliedSpeedKmh: float}>
     */
    public function detectImpossibleTravel(array $sessions): array
    {
        $withLocation = array_values(array_filter(
            $sessions,
            static fn (array $s) => null !== $s['latitude'] && null !== $s['longitude'],
        ));

        $anomalies = [];
        $count = count($withLocation);
        for ($i = 0; $i < $count; ++$i) {
            for ($j = $i + 1; $j < $count; ++$j) {
                $a = $withLocation[$i];
                $b = $withLocation[$j];

                $seconds = abs($a['at']->getTimestamp() - $b['at']->getTimestamp());
                if ($seconds < self::MIN_INTERVAL_SECONDS) {
                    continue;
                }

                $distanceKm = self::haversineKm((float) $a['latitude'], (float) $a['longitude'], (float) $b['latitude'], (float) $b['longitude']);
                $hours = $seconds / 3600;
                $speedKmh = $distanceKm / $hours;

                if ($speedKmh > self::IMPOSSIBLE_SPEED_KMH) {
                    $anomalies[] = [
                        'sessionIdA' => $a['sessionId'],
                        'sessionIdB' => $b['sessionId'],
                        'distanceKm' => round($distanceKm, 1),
                        'impliedSpeedKmh' => round($speedKmh, 1),
                    ];
                }
            }
        }

        return $anomalies;
    }

    /**
     * @param list<mixed> $activeSessionsOrderedOldestFirst n'importe quelle liste représentant les
     *                                                       sessions actives d'un utilisateur, triée de la plus ancienne à la plus récente
     *
     * @return list<mixed> le sous-ensemble à évincer (tout ce qui dépasse la limite, en partant des
     *                      plus anciennes) — jamais la plus récente, qui vient de s'authentifier
     */
    public function selectSessionsExceedingLimit(array $activeSessionsOrderedOldestFirst, int $maxConcurrent = self::DEFAULT_MAX_CONCURRENT_SESSIONS): array
    {
        $excess = count($activeSessionsOrderedOldestFirst) - $maxConcurrent;

        return $excess > 0 ? array_slice($activeSessionsOrderedOldestFirst, 0, $excess) : [];
    }

    private static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}
