<?php

namespace App\Controller\Admin;

use App\Service\MediaUploader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Upload d'une image insérée DANS le corps d'un article/projet (cf. bouton
 * "image" de assets/controllers/rich_text_controller.js) — distinct de la
 * couverture (son propre champ, sa propre entité Media). Ces images-là ne
 * sont pas persistées comme Media : juste un fichier sur disque référencé
 * par une URL absolue directement dans le HTML sauvegardé (comme ferait
 * Trix/ActionText) — pas de cycle de vie à suivre côté DB, au prix d'un
 * fichier orphelin sur disque si l'admin change d'avis avant d'enregistrer
 * (accepté : même trade-off qu'un upload abandonné dans n'importe quel
 * éditeur web, coût négligeable en espace disque).
 *
 * 🔒 Sécurité : réservé à ROLE_ADMIN.
 */
#[Route('/admin/rich-text', name: 'admin_rich_text_')]
class AdminRichTextController extends AbstractController
{
    #[Route('/upload-image', name: 'upload_image', methods: ['POST'])]
    public function uploadImage(
        Request $request,
        #[Autowire(service: 'app.media_uploader.content')] MediaUploader $uploader,
        #[Autowire(service: 'limiter.admin_upload_image')] RateLimiterFactory $adminUploadImageLimiter,
        #[Autowire('%env(DEFAULT_URI)%')] string $publicBaseUrl,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $limit = $adminUploadImageLimiter->create((string) $this->getUser()?->getUserIdentifier());
        if (false === $limit->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Trop d\'uploads, patientez un instant.'], 429);
        }

        $file = $request->files->get('image');
        if (null === $file) {
            return $this->json(['error' => 'Aucun fichier reçu.'], 400);
        }

        try {
            $result = $uploader->upload($file, 'content');
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        // URL absolue construite depuis DEFAULT_URI (hôte public de l'API,
        // ex. https://api.horlynat.com) — PAS depuis $request->getSchemeAndHttpHost(),
        // qui vaudrait ici l'hôte d'admin (dark.horlynat.com, derrière Cloudflare
        // Access) : une image référencée avec cet hôte serait invisible pour
        // n'importe quel visiteur public du site une fois l'article publié.
        return $this->json(['url' => rtrim($publicBaseUrl, '/').$result['path']]);
    }
}
