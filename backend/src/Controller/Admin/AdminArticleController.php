<?php

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Entity\Media;
use App\Entity\Tag;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use App\Repository\TranslationRepository;
use App\Security\Voter\ArticleVoter;
use App\Service\AuditLogger;
use App\Service\MediaUploader;
use App\Service\NewsletterNotifier;
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

    private const PER_PAGE = 20;

    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(Request $request, ArticleRepository $articleRepository): Response
    {
        $this->denyAccessUnlessGranted(ArticleVoter::VIEW);

        $page = max(1, $request->query->getInt('page', 1));
        $paginator = $articleRepository->findPaginated($page, self::PER_PAGE);
        $total = \count($paginator);

        return $this->render('admin/article/index.html.twig', [
            'articles' => $paginator,
            'total' => $total,
            'currentPage' => $page,
            'totalPages' => (int) ceil($total / self::PER_PAGE) ?: 1,
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
        AuditLogger $auditLogger,
        TranslationRepository $translationRepository,
        NewsletterNotifier $newsletterNotifier,
    ): Response {
        $this->denyAccessUnlessGranted(ArticleVoter::CREATE);

        $article = new Article();
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $article->setSlug($this->uniqueArticleSlug($slugger, $entityManager, $article->getTitle()));

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

            // Après flush() pour disposer d'un id garanti (article tout juste créé).
            $translationRepository->syncFromEntity($article);

            // Tout article créé est public dès sa création (pas de statut
            // brouillon sur cette entité, cf. App\Entity\Article) — la
            // notification part donc systématiquement ici, sans condition.
            $newsletterNotifier->notifyNewContent($article->getTitle(), 'article', $article->getSlug());

            $this->addFlash('success', 'L\'article a été créé avec succès.');

            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin/article/create.html.twig', [
            'form' => $form->createView(),
            'article' => $article,
            'action' => $this->generateUrl('admin_article_create'),
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
        AuditLogger $auditLogger,
        TranslationRepository $translationRepository,
    ): Response {
        $this->denyAccessUnlessGranted(ArticleVoter::EDIT, $article);

        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $article->setSlug($this->uniqueArticleSlug($slugger, $entityManager, $article->getTitle(), $article->getId()));

            // Traitement de l'image (réutilisable sans duplication !)
            $this->handleImageUpload($form, $article, $uploader, $entityManager);

            $auditLogger->log(Article::class, $article->getId(), $article->getTitle(), 'updated');
            $entityManager->flush();

            $translationRepository->syncFromEntity($article);

            $this->addFlash('success', 'L\'article a été mis à jour avec succès.');

            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin/article/update.html.twig', [
            'form' => $form->createView(),
            'article' => $article,
            'action' => $this->generateUrl('admin_article_update', ['slug' => $article->getSlug()]),
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
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ArticleVoter::DELETE, $article);

        if ($this->isCsrfTokenValid('admin_article_delete_'.$article->getId(), $request->request->get('_token'))) {
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
        EntityManagerInterface $entityManager,
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

    /**
     * Génère un slug garanti unique pour un titre donné — ajoute un suffixe
     * numérique ("-2", "-3", ...) si le slug de base est déjà pris par un
     * autre article.
     *
     * Fait explicitement ici plutôt que via #[UniqueEntity(fields: ['slug'])]
     * sur l'entité : Symfony valide l'entité PENDANT handleRequest() (l'écouteur
     * POST_SUBMIT de l'extension Validator), avant que ce contrôleur n'ait la
     * main pour fixer le slug — la contrainte ne voit donc jamais la bonne
     * valeur et ne peut jamais détecter de collision, laissant l'INSERT/UPDATE
     * planter en 500 (UniqueConstraintViolationException) à la place.
     *
     * $excludeId : id de l'article en cours d'édition (update()) — pour ne
     * pas le confondre avec un "autre article" alors qu'il s'agit de
     * lui-même resauvegardé avec le même titre.
     */
    private function uniqueArticleSlug(SluggerInterface $slugger, EntityManagerInterface $entityManager, string $title, ?int $excludeId = null): string
    {
        $base = (string) $slugger->slug($title)->lower();
        $repository = $entityManager->getRepository(Article::class);

        $slug = $base;
        for ($suffix = 2; null !== ($existing = $repository->findOneBy(['slug' => $slug])) && $existing->getId() !== $excludeId; ++$suffix) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
