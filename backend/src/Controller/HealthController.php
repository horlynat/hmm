<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint de supervision (PM2/systemd, Cloudflare health check, uptime monitor).
 * Volontairement minimal : ne renvoie qu'un booléen par dépendance, jamais le
 * détail d'une exception (message/trace), pour ne pas exposer d'information
 * interne (version du driver, chemin de connexion...) sur une route publique.
 */
class HealthController
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    #[Route(path: '/health', name: 'health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $databaseOk = true;
        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            $databaseOk = false;
        }

        return new JsonResponse(
            ['status' => $databaseOk ? 'ok' : 'error', 'checks' => ['database' => $databaseOk]],
            $databaseOk ? 200 : 503,
        );
    }
}
