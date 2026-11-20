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

    /**
     * Sonde de vivacité du conteneur (HEALTHCHECK du Dockerfile, via
     * frankenphp/healthcheck.sh) : prouve qu'un worker PHP a démarré le
     * noyau Symfony et répond — contrairement à l'API admin Caddy (:2019),
     * qui répond dès que Caddy écoute, cache Symfony froid ou PHP en panne.
     * Sans dépendance externe (base) : une coupure MySQL passagère ne doit
     * pas faire retirer le conteneur du routage Traefik (unhealthy) — c'est
     * le rôle de /health ci-dessous.
     */
    #[Route(path: '/health/live', name: 'health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        $response = new JsonResponse(['status' => 'ok']);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
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
