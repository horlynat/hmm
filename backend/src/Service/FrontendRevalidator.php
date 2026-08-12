<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Appelle le webhook /api/revalidate du frontend Next.js pour invalider un
 * tag de cache ISR (cf. frontend/src/app/api/revalidate/route.ts) — déclenché
 * de façon asynchrone par App\MessageHandler\RevalidateFrontendMessageHandler
 * juste après chaque création/modification/suppression d'un contenu publié
 * (cf. App\EventSubscriber\FrontendRevalidationSubscriber).
 *
 * Se désactive proprement (log + no-op) tant qu'aucun secret n'est configuré
 * — même convention que App\Service\OffsiteBackupUploader : ce n'est pas une
 * erreur bloquante, juste un maillon pas encore branché (ex. environnement
 * de review sans frontend déployé).
 */
final class FrontendRevalidator
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $frontendUrl,
        // Chaîne vide par défaut (jamais null) : même convention que les
        // identifiants OFFSITE_S3_* — non défini tant que le secret partagé
        // avec le frontend n'est pas configuré, rempli via secret Docker en prod.
        private readonly string $secret = '',
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->secret;
    }

    public function revalidate(string $tag): void
    {
        if (!$this->isConfigured()) {
            $this->logger->info('FrontendRevalidator : aucun secret configuré, revalidation ignorée.', [
                'tag' => $tag,
            ]);

            return;
        }

        if (!str_starts_with($this->frontendUrl, 'https://')) {
            // Pas bloquant (le défaut .env local est http://localhost:3000) : un
            // simple garde-fou pour repérer en prod un FRONTEND_URL mal configuré
            // qui ferait transiter le secret en clair sur le réseau.
            $this->logger->warning('FrontendRevalidator : FRONTEND_URL n\'utilise pas le schéma https — le secret transiterait en clair.', [
                'frontendUrl' => $this->frontendUrl,
            ]);
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->frontendUrl, '/').'/api/revalidate', [
                'headers' => [
                    'x-revalidate-secret' => $this->secret,
                    'Content-Type' => 'application/json',
                ],
                'json' => ['tag' => $tag],
                // Le frontend ne fait qu'invalider une entrée de cache mémoire
                // (revalidateTag) — un délai court suffit, pas question de
                // bloquer le worker Messenger si le frontend est indisponible.
                // "timeout" (idle) ET "max_duration" (durée totale) : le premier
                // seul n'aurait pas de plafond ferme si la réponse arrivait par
                // paquets espacés de moins de 5s chacun.
                'timeout' => 5,
                'max_duration' => 5,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                // Erreur "logique" (secret invalide, tag manquant...) : pas la
                // peine de laisser Messenger réessayer, un retry renverrait la
                // même erreur.
                $this->logger->error('FrontendRevalidator : le webhook a répondu en erreur.', [
                    'tag' => $tag,
                    'status' => $statusCode,
                ]);

                return;
            }

            $this->logger->info('FrontendRevalidator : revalidation déclenchée.', ['tag' => $tag]);
        } catch (\Throwable $e) {
            // Panne réseau/timeout : transitoire, contrairement à une erreur
            // HTTP ci-dessus — Messenger réessaiera (retry_strategy async)
            // avant de finir en file "failed" si le frontend reste injoignable.
            $this->logger->error('FrontendRevalidator : échec d\'appel du webhook.', [
                'tag' => $tag,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
