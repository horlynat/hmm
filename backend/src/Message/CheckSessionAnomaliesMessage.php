<?php

namespace App\Message;

/**
 * Déclenche la détection de "voyage impossible" (cf. SessionAnomalyDetector)
 * sur les sessions actives d'un utilisateur, juste après une connexion.
 * Asynchrone : la géolocalisation par IP fait un appel HTTP externe (via
 * GeolocationService, cf. EnrichLoginLocationMessage pour le même principe),
 * qu'on ne veut jamais sur le chemin critique d'une connexion.
 */
class CheckSessionAnomaliesMessage
{
    public function __construct(
        public readonly int $userId,
    ) {
    }
}
