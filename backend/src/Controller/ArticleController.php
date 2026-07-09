<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Media;
use App\Entity\Tag;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use App\Service\MediaUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Contrôleur ArticleController
 *
 * Gère le CRUD des articles dans le dashboard admin :
 * - index : liste des articles
 * - create : création d’un nouvel article
 * - read : lecture d’un article
 * - update : mise à jour d’un article
 * - delete : suppression d’un article
 */
#[Route('/dashboard/article', name: 'article_')]
final class ArticleController extends AbstractController
{
    /**
     * 📌 Liste des articles
     *
     * Route : /dashboard/article/index
     * Méthode : GET
     *
     * @param ArticleRepository $articleRepository Repository Doctrine pour récupérer les articles.
     * @return Response Vue Twig affichant la liste des articles.
     */
    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(ArticleRepository $articleRepository): Response
    {
        return $this->render('article/index.html.twig', [
            'articles' => $articleRepository->findAll(),
        ]);
    }

    /**
     * 📌 Créer un nouvel article
     *
     * Route : /dashboard/article/create
     * Méthodes : GET, POST
     *
     * - Génère un slug à partir du titre.
     * - Upload l’image si présente via MediaUploader.
     * - Ajoute un tag par défaut si aucun n’est sélectionné.
     * - Persiste l’article et ses médias en base.
     */
    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        MediaUploader $uploader
    ): Response {
        $article = new Article();
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Génération du slug
            $article->setSlug($slugger->slug($article->getTitle())->lower());

            // Upload image via le champ du formulaire
            $imageFile = $form->has('media') ? $form->get('media')->getData() : null;

            if ($imageFile instanceof UploadedFile) {
                $result = $uploader->upload($imageFile, 'articles');

                $media = new Media();
                $media->setFilePath($result['path']);
                $media->setMimeType($result['mimeType']);
                $media->setSize($result['size']);
                $media->setUploadedAt($result['uploadedAt']);
                $media->setAltText($article->getTitle()); // accessibilité

                $entityManager->persist($media);
                $article->addMedia($media);
            }

            // Ajout d’un tag par défaut si aucun n’est choisi
            if ($article->getTags()->isEmpty()) {
                $defaultTag = $entityManager->getRepository(Tag::class)->findOneBy(['name' => 'Par défaut']);
                if ($defaultTag) {
                    $article->addTag($defaultTag);
                }
            }

            $entityManager->persist($article);
            $entityManager->flush();

            $this->addFlash('success', 'Article créé avec succès.');
            return $this->redirectToRoute('article_index');
        }

        return $this->render('article/create.html.twig', [
            'form'         => $form,
            'article'      => $article,
            'action'       => $this->generateUrl('article_create'),
            'button_label' => 'Enregistrer l\'article',
        ]);
    }

    /**
     * 📌 Lire un article
     *
     * Route : /dashboard/article/{slug}
     * Méthode : GET
     *
     * @param Article $article Injection automatique via MapEntity (slug).
     * @return Response Vue Twig affichant le contenu de l’article.
     */
    #[Route('/{slug}', name: 'read', methods: ['GET'])]
    public function read(#[MapEntity(mapping: ['slug' => 'slug'])] Article $article): Response
    {
        return $this->render('article/read.html.twig', [
            'article' => $article,
        ]);
    }

    /**
     * 📌 Mettre à jour un article
     *
     * Route : /dashboard/article/{slug}/update
     * Méthodes : GET, POST
     *
     * - Met à jour le slug si le titre change.
     * - Upload une nouvelle image si présente.
     * - Persiste les modifications.
     */
    #[Route('/{slug}/update', name: 'update', methods: ['GET', 'POST'])]
    public function update(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])] Article $article,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        MediaUploader $uploader
    ): Response {
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $article->setSlug($slugger->slug($article->getTitle())->lower());

            // Upload nouvelle image si présente
            $imageFile = $form->has('media') ? $form->get('media')->getData() : null;

            if ($imageFile instanceof UploadedFile) {
                $result = $uploader->upload($imageFile, 'articles');

                $media = new Media();
                $media->setFilePath($result['path']);
                $media->setMimeType($result['mimeType']);
                $media->setSize($result['size']);
                $media->setUploadedAt($result['uploadedAt']);
                $media->setAltText($article->getTitle());

                $entityManager->persist($media);
                $article->addMedia($media);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Article mis à jour avec succès.');
            return $this->redirectToRoute('article_index');
        }

        return $this->render('article/update.html.twig', [
            'form'         => $form,
            'article'      => $article,
            'action'       => $this->generateUrl('article_update', ['slug' => $article->getSlug()]),
            'button_label' => 'Mettre à jour l\'article',
        ]);
    }

    /**
     * 📌 Supprimer un article
     *
     * Route : /dashboard/article/{slug}/delete
     * Méthode : POST
     *
     * - Vérifie le token CSRF.
     * - Supprime l’article si valide.
     */
    #[Route('/{slug}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])] Article $article,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $article->getId(), $request->request->get('_token'))) {
            $entityManager->remove($article);
            $entityManager->flush();
            $this->addFlash('success', 'Article supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('article_index');
    }
}
