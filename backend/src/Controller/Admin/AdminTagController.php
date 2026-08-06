<?php

namespace App\Controller\Admin;

use App\Entity\Tag;
use App\Form\TagType;
use App\Repository\TagRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur pour la gestion des étiquettes (Tags) d'articles.
 *
 * 🔒 Sécurité :
 * - Réservé exclusivement aux utilisateurs dotés du rôle ROLE_ADMIN.
 * - Validation stricte des jetons CSRF pour les actions destructrices.
 */
#[Route('/admin/tag', name: 'admin_tag_')]
final class AdminTagController extends AbstractController
{
    // =========================================================================
    // 📌 LISTE DES TAGS
    // =========================================================================

    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(TagRepository $tagRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/tag/index.html.twig', [
            'tags' => $tagRepository->findAll(),
        ]);
    }

    // =========================================================================
    // 📌 CRÉATION D'UN TAG
    // =========================================================================

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $tag = new Tag();
        $form = $this->createForm(TagType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($tag);
            $entityManager->flush();

            $auditLogger->log(Tag::class, $tag->getId(), $tag->getName(), 'created');
            $entityManager->flush();

            $this->addFlash('success', 'Le mot-clé a été créé avec succès.');
            return $this->redirectToRoute('admin_tag_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/tag/create.html.twig', [
            'tag' => $tag,
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 CONSULTATION D'UN TAG
    // =========================================================================

    #[Route('/{id}/read', name: 'read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(Tag $tag): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/tag/read.html.twig', [
            'tag' => $tag,
        ]);
    }

    // =========================================================================
    // 📌 MISE À JOUR D'UN TAG
    // =========================================================================

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Tag $tag, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(TagType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $auditLogger->log(Tag::class, $tag->getId(), $tag->getName(), 'updated');
            $entityManager->flush();

            $this->addFlash('success', 'Le mot-clé a été mis à jour avec succès.');
            return $this->redirectToRoute('admin_tag_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/tag/update.html.twig', [
            'tag' => $tag,
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 SUPPRESSION D'UN TAG
    // =========================================================================

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Tag $tag, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Uniformisation de la clé de hachage et de la capture du paramètre de requête
        if ($this->isCsrfTokenValid('admin_tag_delete_' . $tag->getId(), $request->request->get('_token'))) {
            $auditLogger->log(Tag::class, $tag->getId(), $tag->getName(), 'deleted');
            $entityManager->remove($tag);
            $entityManager->flush();
            
            $this->addFlash('success', 'Le mot-clé a été supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Action de suppression annulée.');
        }

        return $this->redirectToRoute('admin_tag_index', [], Response::HTTP_SEE_OTHER);
    }
}