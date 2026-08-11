<?php

namespace App\Service;

use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use Psr\Log\LoggerInterface;

/**
 * Copie hors-site (S3-compatible : Cloudflare R2, Backblaze B2, OVH...) d'un
 * fichier tout juste écrit dans public/uploads — appelée de façon
 * asynchrone par App\MessageHandler\OffsiteBackupMessageHandler juste après
 * chaque upload (cf. App\Service\MediaUploader), pour réduire la fenêtre de
 * perte à quelques secondes au lieu d'attendre la prochaine sauvegarde
 * périodique (cf. infra/scripts/backup.sh, qui ne couvre que la base).
 *
 * Se désactive proprement (log + no-op) tant qu'aucun provider n'est
 * configuré — même logique que backup.sh avec AGE_RECIPIENT : ce n'est pas
 * une erreur bloquante, juste un maillon pas encore branché.
 */
final class OffsiteBackupUploader
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $uploadDir,
        // Chaînes vides par défaut (jamais null) : même convention que
        // GEMINI_API_KEY/ANTHROPIC_API_KEY dans .env — non définies tant que
        // le provider n'est pas choisi, remplies via secrets Docker en prod.
        private readonly string $endpoint = '',
        private readonly string $bucket = '',
        private readonly string $accessKeyId = '',
        private readonly string $secretAccessKey = '',
        private readonly string $region = 'auto',
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->endpoint
            && '' !== $this->bucket
            && '' !== $this->accessKeyId
            && '' !== $this->secretAccessKey;
    }

    /**
     * @param string $relativePath Chemin relatif à public/uploads (ex: "about/portrait-abc123.jpg")
     */
    public function upload(string $relativePath): void
    {
        if (!$this->isConfigured()) {
            $this->logger->info('OffsiteBackupUploader : aucun provider configuré, copie hors-site ignorée.', [
                'path' => $relativePath,
            ]);

            return;
        }

        $localPath = rtrim($this->uploadDir, '/') . '/' . ltrim($relativePath, '/');
        if (!is_file($localPath)) {
            $this->logger->warning('OffsiteBackupUploader : fichier local introuvable, copie hors-site annulée.', [
                'path' => $relativePath,
            ]);

            return;
        }

        $client = new S3Client([
            'endpoint' => $this->endpoint,
            'region' => $this->region,
            'accessKeyId' => $this->accessKeyId,
            'accessKeySecret' => $this->secretAccessKey,
            // Les providers S3-compatible (R2, B2, OVH...) exigent le style
            // "path" (bucket.example.com/key est réservé au vrai AWS S3 avec
            // DNS wildcard) — sans ça, la requête part vers un host inexistant.
            'pathStyleEndpoint' => 'true',
        ]);

        try {
            $client->putObject(new PutObjectRequest([
                'Bucket' => $this->bucket,
                'Key' => $relativePath,
                'Body' => file_get_contents($localPath),
            ]));

            $this->logger->info('OffsiteBackupUploader : copie hors-site réussie.', ['path' => $relativePath]);
        } catch (\Throwable $e) {
            // Ne jamais faire échouer l'upload principal pour une panne de la
            // copie de secours — Messenger réessaiera (retry_strategy async)
            // avant de finir en file "failed" si le provider reste injoignable.
            $this->logger->error('OffsiteBackupUploader : échec de la copie hors-site.', [
                'path' => $relativePath,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
