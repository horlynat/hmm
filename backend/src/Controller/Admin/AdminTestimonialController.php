<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\Testimonial;
use App\Form\TestimonialType;
use App\Repository\TestimonialRepository;
use App\Service\MediaUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapEntity;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur pour la gestion des témoignages clients.
 *
 * 🔒 Sécurité :
 * - Réservé exclusivement aux utilisateurs dotés du rôle ROLE_ADMIN.
 * - Validation stricte des jetons CSRF pour les actions de publication et de suppression.
 *
 * ✅ Fonctionnalités :
 * - CRUD complet avec upload média.
 * - Modération : publication (publishedAt renseigné) / dépublication (publishedAt à null).
 */
#[Route('/admin/testimonial', name: 'admin_testimonial_')]
final class AdminTestimonialController extends AbstractController
{
    // =========================================================================
    // 📌 LISTE DES TÉMOIGNAGES
    // =========================================================================

    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(Request $request, TestimonialRepository $testimonialRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $statusFilter = $request->query->get('status', '');
        $search = trim((string) $request->query->get('search', ''));

        $queryBuilder = $testimonialRepository->createQueryBuilder('t')
            ->orderBy('t.id', 'DESC');

        if ('published' === $statusFilter) {
            $queryBuilder->andWhere('t.publishedAt IS NOT NULL');
        } elseif ('pending' === $statusFilter) {
            $queryBuilder->andWhere('t.publishedAt IS NULL');
        }

        if ('' !== $search) {
            $queryBuilder->andWhere('t.author LIKE :search OR t.content LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        return $this->render('admin/testimonial/testimonials.html.twig', [
            'testimonials' => $queryBuilder->getQuery()->getResult(),
            'filters' => [
                'status' => $statusFilter,
                'search' => $search,
            ],
        ]);
    }

    // =========================================================================
    // 📌 CRÉATION D'UN TÉMOIGNAGE
    // =========================================================================

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, MediaUploader $mediaUploader): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $testimonial = new Testimonial();
        $form = $this->createForm(TestimonialType::class, $testimonial);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleMediaUpload($testimonial, $form, $entityManager, $mediaUploader);

            $entityManager->persist($testimonial);
            $entityManager->flush();

            $this->addFlash('success', 'Le témoignage a été créé avec succès (en attente de publication).');

            return $this->redirectToRoute('admin_testimonial_index');
        }

        return $this->render('admin/testimonial/create.html.twig', [
            'testimonial' => $testimonial,
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 DÉTAIL D'UN TÉMOIGNAGE
    // =========================================================================

    #[Route('/{id}/read', name: 'read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(Testimonial $testimonial): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/testimonial/read.html.twig', [
            'testimonial' => $testimonial,
        ]);
    }

    // =========================================================================
    // 📌 MISE À JOUR D'UN TÉMOIGNAGE
    // =========================================================================

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function update(Testimonial $testimonial, Request $request, EntityManagerInterface $entityManager, MediaUploader $mediaUploader): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(TestimonialType::class, $testimonial);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleMediaUpload($testimonial, $form, $entityManager, $mediaUploader);

            $entityManager->flush();
            $this->addFlash('success', 'Le témoignage a été mis à jour avec succès.');

            return $this->redirectToRoute('admin_testimonial_index');
        }

        return $this->render('admin/testimonial/update.html.twig', [
            'testimonial' => $testimonial,
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 PUBLICATION D'UN TÉMOIGNAGE
    // =========================================================================

    #[Route('/{id}/publish', name: 'publish', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function publish(Testimonial $testimonial, EntityManagerInterface $entityManager, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('testimonial_publish_'.$testimonial->getId(), $request->request->get('_token'))) {
            $testimonial->setPublishedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Le témoignage a été publié.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_testimonial_read', ['id' => $testimonial->getId()]);
    }

    // =========================================================================
    // 📌 DÉPUBLICATION D'UN TÉMOIGNAGE
    // =========================================================================

    #[Route('/{id}/unpublish', name: 'unpublish', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function unpublish(Testimonial $testimonial, EntityManagerInterface $entityManager, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('testimonial_publish_'.$testimonial->getId(), $request->request->get('_token'))) {
            $testimonial->setPublishedAt(null);
            $entityManager->flush();

            $this->addFlash('success', 'Le témoignage a été dépublié.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_testimonial_read', ['id' => $testimonial->getId()]);
    }

    // =========================================================================
    // 📌 SUPPRESSION D'UN MÉDIA
    // =========================================================================

    #[Route('/{id}/media/{mediaId}/delete', name: 'delete_media', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteMedia(
        Testimonial $testimonial,
        #[MapEntity(id: 'mediaId')] Media $media,
        EntityManagerInterface $entityManager,
        Request $request,
        MediaUploader $mediaUploader,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('admin_testimonial_delete_media_'.$media->getId(), $request->request->get('_token'))) {
            $mediaUploader->delete(basename($media->getFilePath()), 'testimonials');

            $testimonial->removeMedium($media);
            $entityManager->remove($media);
            $entityManager->flush();

            $this->addFlash('success', 'Média supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_testimonial_read', ['id' => $testimonial->getId()]);
    }

    // =========================================================================
    // 📌 SUPPRESSION D'UN TÉMOIGNAGE
    // =========================================================================

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Testimonial $testimonial, EntityManagerInterface $entityManager, Request $request, MediaUploader $mediaUploader): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('admin_testimonial_delete_'.$testimonial->getId(), $request->request->get('_token'))) {
            foreach ($testimonial->getMedia() as $media) {
                $mediaUploader->delete(basename($media->getFilePath()), 'testimonials');
            }

            $entityManager->remove($testimonial);
            $entityManager->flush();

            $this->addFlash('success', 'Le témoignage a été supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Action de suppression annulée.');
        }

        return $this->redirectToRoute('admin_testimonial_index', [], Response::HTTP_SEE_OTHER);
    }

    // =========================================================================
    // 🔧 MÉTHODES UTILITAIRES PRIVÉES
    // =========================================================================

    private function handleMediaUpload(
        Testimonial $testimonial,
        FormInterface $form,
        EntityManagerInterface $entityManager,
        MediaUploader $mediaUploader,
    ): void {
        $mediaFiles = $form->get('media')->getData();

        if ($mediaFiles && count($mediaFiles) > 0) {
            $results = $mediaUploader->uploadMultiple($mediaFiles, 'testimonials');

            foreach ($results as $result) {
                $media = new Media();
                $media
                    ->setFilePath($result['path'])
                    ->setAltText($testimonial->getAuthor() ?? 'Testimonial Media')
                    ->setMimeType($result['mimeType'])
                    ->setSize($result['size'])
                    ->setType($result['type'])
                    ->setUploadedAt($result['uploadedAt']);

                $entityManager->persist($media);
                $testimonial->addMedium($media);
            }
        }
    }
}
