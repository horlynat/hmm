<?php

namespace App\Message;

/**
 * Demande de copie hors-site d'un fichier tout juste uploadé (cf.
 * App\Service\MediaUploader) — traité de façon asynchrone par
 * App\MessageHandler\OffsiteBackupMessageHandler, pour ne pas ralentir la
 * réponse de la requête d'upload elle-même.
 */
class OffsiteBackupMessage
{
    public function __construct(
        /** Chemin relatif à public/uploads (ex: "about/portrait-abc123.jpg"). */
        public readonly string $relativePath,
    ) {
    }
}
