<?php

namespace App\Controller\Admin;

use App\Entity\Offer;
use App\Form\OfferType;
use App\Repository\OfferRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
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
    public function create(Request $request, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $offer = new Offer();
        $form = $this->createForm(OfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($offer);
            $entityManager->flush();
            $auditLogger->log(Offer::class, $offer->getId(), $offer->getTitle(), 'created');
            $entityManager->flush();

            $this->addFlash('success', 'L\'offre a été créée.');

            return $this->redirectToRoute('admin_offer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/offer/create.html.twig', ['offer' => $offer, 'form' => $form]);
    }

    #[Route('/{id}/update', name: 'update', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function update(Request $request, Offer $offer, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $form = $this->createForm(OfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $auditLogger->log(Offer::class, $offer->getId(), $offer->getTitle(), 'updated');
            $entityManager->flush();
            $this->addFlash('success', 'L\'offre a été mise à jour.');

            return $this->redirectToRoute('admin_offer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/offer/update.html.twig', ['offer' => $offer, 'form' => $form]);
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
}
