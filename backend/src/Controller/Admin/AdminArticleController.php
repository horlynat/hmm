<?php

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Entity\Media;
use App\Entity\Tag;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use App\Security\Voter\ArticleVoter;
use App\Service\AuditLogger;
use App\Service\MediaUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Contrôleur pour la gestion des articles du blog dans le dashboard admin.
 *
 * 🔒 Sécurité :
 * - Réservé exclusivement aux utilisateurs dotés du rôle ROLE_ADMIN.
 * - Protection CSRF stricte sur les suppressions d'articles.
 */
#[Route('/admin/article', name: 'admin_article_')]
final class AdminArticleController extends AbstractController
{
    // =========================================================================
    // 📌 LISTE DES ARTICLES
    // =========================================================================

    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(ArticleRepository $articleRepository): Response
    {
        $this->denyAccessUnlessGranted(ArticleVoter::VIEW);

        return $this->render('admin/article/index.html.twig', [
            'articles' => $articleRepository->findAll(),
        ]);
    }

    // =========================================================================
    // 📌 CRÉATION D'UN ARTICLE
    // =========================================================================

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        MediaUploader $uploader,
        AuditLogger $auditLogger
    ): Response {
        $this->denyAccessUnlessGranted(ArticleVoter::CREATE);

        $article = new Article();
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $article->setSlug($slugger->slug($article->getTitle())->lower());

            // Extraction et traitement de l'image via la méthode optimisée
            $this->handleImageUpload($form, $article, $uploader, $entityManager);

            // Tag par défaut automatique si aucun choix
            if ($article->getTags()->isEmpty()) {
                $defaultTag = $entityManager->getRepository(Tag::class)->findOneBy(['name' => 'Par défaut']);
                if ($defaultTag) {
                    $article->addTag($defaultTag);
                }
            }

            $entityManager->persist($article);
            $entityManager->flush();

            $auditLogger->log(Article::class, $article->getId(), $article->getTitle(), 'created');
            $entityManager->flush();

            $this->addFlash('success', 'L\'article a été créé avec succès.');
            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin/article/create.html.twig', [
            'form'         => $form->createView(),
            'article'      => $article,
            'action'       => $this->generateUrl('admin_article_create'),
            'button_label' => 'Enregistrer l\'article',
        ]);
    }

    // =========================================================================
    // 📌 CONSULTATION D'UN ARTICLE
    // =========================================================================

    #[Route('/{slug}', name: 'read', methods: ['GET'])]
    public function read(#[MapEntity(mapping: ['slug' => 'slug'])] Article $article): Response
    {
        $this->denyAccessUnlessGranted(ArticleVoter::VIEW, $article);

        return $this->render('admin/article/read.html.twig', [
            'article' => $article,
        ]);
    }

    // =========================================================================
    // 📌 MISE À JOUR D'UN ARTICLE
    // =========================================================================

    #[Route('/{slug}/update', name: 'update', methods: ['GET', 'POST'])]
    public function update(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])] Article $article,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        MediaUploader $uploader,
        AuditLogger $auditLogger
    ): Response {
        $this->denyAccessUnlessGranted(ArticleVoter::EDIT, $article);

        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $article->setSlug($slugger->slug($article->getTitle())->lower());

            // Traitement de l'image (réutilisable sans duplication !)
            $this->handleImageUpload($form, $article, $uploader, $entityManager);

            $auditLogger->log(Article::class, $article->getId(), $article->getTitle(), 'updated');
            $entityManager->flush();

            $this->addFlash('success', 'L\'article a été mis à jour avec succès.');
            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin/article/update.html.twig', [
            'form'         => $form->createView(),
            'article'      => $article,
            'action'       => $this->generateUrl('admin_article_update', ['slug' => $article->getSlug()]), 
            'button_label' => 'Mettre à jour l\'article',
        ]);
    }

    // =========================================================================
    // 📌 SUPPRESSION D'UN ARTICLE
    // =========================================================================

    #[Route('/{slug}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])] Article $article,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger
    ): Response {
        $this->denyAccessUnlessGranted(ArticleVoter::DELETE, $article);

        if ($this->isCsrfTokenValid('admin_article_delete_' . $article->getId(), $request->request->get('_token'))) {
            $auditLogger->log(Article::class, $article->getId(), $article->getTitle(), 'deleted');
            $entityManager->remove($article);
            $entityManager->flush();
            
            $this->addFlash('success', 'L\'article a été supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Action de suppression annulée.');
        }

        return $this->redirectToRoute('admin_article_index');
    }

    // =========================================================================
    // 🔒 MÉTHODES INTERNES PRIVÉES (LOGIQUE CENTRALISÉE)
    // =========================================================================

    /**
     * Gère l'extraction, le téléversement et l'association d'un fichier média à un article.
     */
    private function handleImageUpload(
        FormInterface $form, 
        Article $article, 
        MediaUploader $uploader, 
        EntityManagerInterface $entityManager
    ): void {
        $imageFile = $form->has('media') ? $form->get('media')->getData() : null;

        if ($imageFile instanceof UploadedFile) {
            $result = $uploader->upload($imageFile, 'articles');

            $media = new Media();
            $media->setFilePath($result['path'])
                  ->setMimeType($result['mimeType'])
                  ->setSize($result['size'])
                  ->setUploadedAt($result['uploadedAt'])
                  ->setAltText($article->getTitle());

            $entityManager->persist($media);
            $article->addMedia($media);
        }
    }
}