<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\Offer;
use App\Form\OfferType;
use App\Repository\OfferRepository;
use App\Service\AuditLogger;
use App\Service\MediaUploader;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Offres du site public (refonte 2026-11) — seul point d'écriture des offres
 * (l'API est en lecture seule, cf. OfferApiResource). ROLE_ADMIN, CSRF sur
 * chaque formulaire et sur la suppression, journal d'audit.
 */
#[Route('/admin/offer', name: 'admin_offer_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminOfferController extends AbstractController
{
    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(OfferRepository $offerRepository): Response
    {
        return $this->render('admin/offer/index.html.twig', [
            'offers' => $offerRepository->findAllOrdered(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, AuditLogger $auditLogger, #[Autowire(service: 'app.media_uploader.offer')] MediaUploader $mediaUploader): Response
    {
        $offer = new Offer();
        $form = $this->createForm(OfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->syncCoverImageFromForm($form, $offer, $entityManager, $mediaUploader);
            $entityManager->persist($offer);
            if ($this->flushOrFlagDuplicateSlug($entityManager, $form)) {
                $auditLogger->log(Offer::class, $offer->getId(), $offer->getTitle(), 'created');
                $entityManager->flush();
                $this->addFlash('success', 'L\'offre a été créée.');

                return $this->redirectToRoute('admin_offer_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('admin/offer/create.html.twig', ['offer' => $offer, 'form' => $form], $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    #[Route('/{id}/update', name: 'update', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function update(Request $request, Offer $offer, EntityManagerInterface $entityManager, AuditLogger $auditLogger, #[Autowire(service: 'app.media_uploader.offer')] MediaUploader $mediaUploader): Response
    {
        $form = $this->createForm(OfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->syncCoverImageFromForm($form, $offer, $entityManager, $mediaUploader);
            $auditLogger->log(Offer::class, $offer->getId(), $offer->getTitle(), 'updated');
            if ($this->flushOrFlagDuplicateSlug($entityManager, $form)) {
                $this->addFlash('success', 'L\'offre a été mise à jour.');

                return $this->redirectToRoute('admin_offer_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('admin/offer/update.html.twig', ['offer' => $offer, 'form' => $form], $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Offer $offer, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        if (!$this->isCsrfTokenValid('delete_offer'.$offer->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide : l\'offre n\'a pas été supprimée.');

            return $this->redirectToRoute('admin_offer_index', [], Response::HTTP_SEE_OTHER);
        }

        $auditLogger->log(Offer::class, $offer->getId(), $offer->getTitle(), 'deleted');
        $entityManager->remove($offer);
        $entityManager->flush();
        $this->addFlash('success', 'L\'offre a été supprimée.');

        return $this->redirectToRoute('admin_offer_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Image illustrative réelle (Offer::coverImage) — même logique que
     * ProjectInfo::coverImage (AdminProjectController::syncProjectInfoFromForm) :
     * un nouvel envoi remplace l'actuelle (l'ancien Media reste en base, pas de
     * suppression physique automatique) ; "retirer" ne s'applique que si aucun
     * nouveau fichier n'est envoyé.
     */
    private function syncCoverImageFromForm(FormInterface $form, Offer $offer, EntityManagerInterface $entityManager, MediaUploader $mediaUploader): void
    {
        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $coverFile */
        $coverFile = $form->get('coverImage')->getData();
        $removeCoverImage = (bool) $form->get('removeCoverImage')->getData();

        if (null !== $coverFile) {
            $result = $mediaUploader->upload($coverFile, 'offers');
            $media = new Media();
            $media
                ->setFilePath($result['path'])
                ->setAltText($offer->getTitle())
                ->setMimeType($result['mimeType'])
                ->setSize($result['size'])
                ->setType($result['type'])
                ->setUploadedAt($result['uploadedAt']);
            $entityManager->persist($media);
            $offer->setCoverImage($media);
        } elseif ($removeCoverImage) {
            $offer->setCoverImage(null);
        }
    }

    /**
     * UniqueEntity ne protège pas d'une course entre deux enregistrements du
     * même slug : l'index unique lève alors une exception, qui donnait une
     * 500. On la ramène à une erreur sur le champ (contre-audit R1).
     */
    private function flushOrFlagDuplicateSlug(EntityManagerInterface $entityManager, FormInterface $form): bool
    {
        try {
            $entityManager->flush();

            return true;
        } catch (UniqueConstraintViolationException) {
            $form->get('slug')->addError(new FormError('Ce slug est déjà utilisé par une autre offre.'));

            return false;
        }
    }
}
