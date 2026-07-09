<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service MediaUploader
 *
 * Gère l’upload, la validation, la sécurisation et la gestion des fichiers.
 * - Validation MIME et taille
 * - Génération de noms sécurisés et uniques
 * - Déduction automatique du type (image, vidéo, audio, document)
 * - Logging des opérations
 * - Support upload multiple
 */
class MediaUploader
{
    private string $targetDirectory;
    private SluggerInterface $slugger;
    private LoggerInterface $logger;
    private array $allowedMimeTypes;
    private int $maxFileSize;

    public function __construct(
        string $targetDirectory,
        SluggerInterface $slugger,
        LoggerInterface $logger,
        array $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'],
        int $maxFileSize = 5242880 // 5 Mo
    ) {
        $this->targetDirectory = $targetDirectory;
        $this->slugger = $slugger;
        $this->logger = $logger;
        $this->allowedMimeTypes = $allowedMimeTypes;
        $this->maxFileSize = $maxFileSize;
    }

    /**
     * Upload un fichier et retourne ses métadonnées enrichies.
     *
     * @param UploadedFile $file Fichier uploadé
     * @param string|null $subDirectory Sous-répertoire optionnel
     *
     * @return array Métadonnées du fichier (nom, chemin, type MIME, taille, hash, date, type déduit)
     *
     * @throws \RuntimeException Si le fichier est invalide ou l’upload échoue
     */
    public function upload(UploadedFile $file, ?string $subDirectory = null): array
    {
        if (!$file instanceof UploadedFile) {
            throw new \RuntimeException("Aucun fichier valide fourni.");
        }

        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        // Validation MIME
        if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
            throw new \RuntimeException("Type de fichier non autorisé : " . $mimeType);
        }

        // Validation taille
        if ($size > $this->maxFileSize) {
            throw new \RuntimeException("Le fichier dépasse la taille maximale autorisée.");
        }

        // Génération nom unique
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $hash = hash('sha256', $originalFilename . time());
        $newFilename = $safeFilename . '-' . substr($hash, 0, 12) . '.' . $file->guessExtension();

        // Répertoire cible
        $targetDir = $this->getTargetDirectory();
        if ($subDirectory) {
            $targetDir .= '/' . $subDirectory;
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
        }

        try {
            $file->move($targetDir, $newFilename);
            chmod($targetDir . '/' . $newFilename, 0644);

            // Déduction du type
            $type = $this->deduceType($mimeType);

            // Logging
            $this->logger->info("Fichier uploadé", [
                'filename' => $newFilename,
                'mimeType' => $mimeType,
                'size' => $size,
                'type' => $type,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'location' => $subDirectory ?? 'root',
            ]);
        } catch (FileException $e) {
            $this->logger->error("Erreur upload fichier", ['error' => $e->getMessage()]);
            throw new \RuntimeException("Erreur lors de l'upload du fichier.");
        }

        return [
            'filename' => $newFilename,
            'path' => '/uploads/' . ($subDirectory ? $subDirectory . '/' : '') . $newFilename,
            'mimeType' => $mimeType,
            'size' => $size,
            'uploadedAt' => new \DateTimeImmutable(),
            'hash' => $hash,
            'type' => $type, // ✅ type calculé
        ];
    }

    /**
     * Upload plusieurs fichiers en une seule fois.
     *
     * @param UploadedFile[] $files Tableau de fichiers
     * @param string|null $subDirectory Sous-répertoire optionnel
     *
     * @return array[] Liste des métadonnées pour chaque fichier
     */
    public function uploadMultiple(array $files, ?string $subDirectory = null): array
    {
        $results = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $results[] = $this->upload($file, $subDirectory);
            }
        }
        return $results;
    }

    /**
     * Déduit le type logique du fichier à partir du mimeType.
     *
     * @param string $mimeType
     * @return string Type déduit (image, video, audio, document)
     */
    private function deduceType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }
        return 'document';
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }

    public function delete(string $filename, ?string $subDirectory = null): bool
    {
        $filePath = $this->getTargetDirectory() . '/' . ($subDirectory ? $subDirectory . '/' : '') . $filename;
        if (file_exists($filePath)) {
            unlink($filePath);
            $this->logger->info("Fichier supprimé", ['filename' => $filename]);
            return true;
        }
        return false;
    }

    public function generateThumbnail(string $filePath, int $width = 200, int $height = 200): ?string
    {
        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            return null;
        }

        $src = imagecreatefromstring(file_get_contents($filePath));
        $thumb = imagecreatetruecolor($width, $height);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $width, $height, $imageInfo[0], $imageInfo[1]);

        $thumbPath = $filePath . '_thumb.jpg';
        imagejpeg($thumb, $thumbPath, 85);

        $this->logger->info("Thumbnail généré", ['path' => $thumbPath]);

        return $thumbPath;
    }
}
