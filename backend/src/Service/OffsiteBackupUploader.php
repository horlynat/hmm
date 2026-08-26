<?php

namespace App\Service;

use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use Psr\Log\LoggerInterface;

/**
 * Copie hors-site (S3-compatible : Cloudflare R2, Backblaze B2, OVH...) d'un
 * fichier local déjà écrit sur le VPS — deux appelants :
 * - App\MessageHandler\OffsiteBackupMessageHandler, de façon asynchrone juste
 *   après chaque upload (cf. App\Service\MediaUploader), pour réduire la
 *   fenêtre de perte à quelques secondes au lieu d'attendre la prochaine
 *   sauvegarde périodique ;
 * - App\Service\DatabaseBackupService, juste après chaque dump réussi, pour
 *   la copie hors-site chiffrée de la base (cf. uploadFile()).
 *
 * Se désactive proprement (log + no-op) tant qu'aucun provider n'est
 * configuré — même logique que DatabaseBackupService avec AGE_RECIPIENT : ce
 * n'est pas une erreur bloquante, juste un maillon pas encore branché.
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
        $localPath = rtrim($this->uploadDir, '/') . '/' . ltrim($relativePath, '/');
        $this->uploadFile($localPath, $relativePath);
    }

    /**
     * Version générique : chemin local absolu -> clé S3 arbitraire, sans
     * supposer que le fichier vit sous public/uploads (cf. les dumps de
     * DatabaseBackupService, qui vivent sous var/backups).
     */
    public function uploadFile(string $localPath, string $remoteKey): void
    {
        if (!$this->isConfigured()) {
            $this->logger->info('OffsiteBackupUploader : aucun provider configuré, copie hors-site ignorée.', [
                'path' => $remoteKey,
            ]);

            return;
        }

        if (!is_file($localPath)) {
            $this->logger->warning('OffsiteBackupUploader : fichier local introuvable, copie hors-site annulée.', [
                'path' => $remoteKey,
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
                'Key' => $remoteKey,
                'Body' => file_get_contents($localPath),
            ]));

            $this->logger->info('OffsiteBackupUploader : copie hors-site réussie.', ['path' => $remoteKey]);
        } catch (\Throwable $e) {
            // Ne jamais faire échouer l'upload principal pour une panne de la
            // copie de secours — Messenger réessaiera (retry_strategy async)
            // avant de finir en file "failed" si le provider reste injoignable
            // (cas de MediaUploader) ; DatabaseBackupService, lui, capture et
            // journalise cette exception sans jamais la laisser remonter (la
            // sauvegarde locale a déjà réussi, ce n'est qu'un maillon en plus).
            $this->logger->error('OffsiteBackupUploader : échec de la copie hors-site.', [
                'path' => $remoteKey,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
