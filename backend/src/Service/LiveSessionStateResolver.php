<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Résout l'état "vivant" des sessions PHP (table `sessions`, gérée par
 * PdoSessionHandler, non mappée en entité Doctrine — accès SQL brut
 * nécessaire) : source UNIQUE de vérité pour "cette session est-elle active,
 * expirée, ou terminée ?".
 *
 * Centralisé ici après un bug constaté dans AdminSecuritySessionController :
 * `sess_lifetime` est un TIMESTAMP ABSOLU d'expiration (time() + gc_maxlifetime,
 * réécrit à chaque accès — cf. PdoSessionHandler::getUpdateStatement()/
 * getInsertStatement() côté vendor), pas une durée à additionner à sess_time
 * malgré son nom trompeur. L'ancien calcul (sess_time + sess_lifetime >= now())
 * produisait un horizon vers l'an 2081 pour chaque ligne : le statut "expirée"
 * ne se déclenchait quasiment jamais. Ne PAS recalculer cette formule ailleurs
 * dans le code — passer par ce service, pour ne pas réintroduire la confusion
 * dans un second endroit.
 */
final class LiveSessionStateResolver
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, array{active: bool, lastActivityAt: int}> indexé par sess_id.
     *         Une clé absente == session déjà détruite ("terminée").
     */
    public function resolveAll(): array
    {
        $rows = $this->connection->executeQuery('SELECT sess_id, sess_time, sess_lifetime FROM sessions')
            ->fetchAllAssociative();

        $now = time();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['sess_id']] = [
                'active' => (int) $row['sess_lifetime'] >= $now,
                'lastActivityAt' => (int) $row['sess_time'],
            ];
        }

        return $result;
    }

    public function isActive(string $sessionId): bool
    {
        $lifetime = $this->connection->executeQuery(
            'SELECT sess_lifetime FROM sessions WHERE sess_id = :id',
            ['id' => $sessionId],
        )->fetchOne();

        return false !== $lifetime && (int) $lifetime >= time();
    }
}
