<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Tag;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface; // Import indispensable

#[Route('/dashboard/article', name: 'article_')]
final class ArticleController extends AbstractController
{
    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(ArticleRepository $articleRepository): Response
    {
        return $this->render('article/index.html.twig', [
            'articles' => $articleRepository->findAll(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $article = new Article();
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Génération du slug via SluggerInterface
            $slug = $slugger->slug($article->getTitle())->lower();
            $article->setSlug($slug);

            if ($article->getTags()->isEmpty()) {
                $defaultTag = $entityManager->getRepository(Tag::class)->findOneBy(['name' => 'Par défaut']);
                if ($defaultTag) {
                    $article->addTag($defaultTag);
                }
            }
            $entityManager->persist($article);
            $entityManager->flush();
            $this->addFlash('success', 'L\'article a été créé avec succès.');

            return $this->redirectToRoute('article_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('article/create.html.twig', [
            'article' => $article,
            'form' => $form,
        ]);
    }

    #[Route('/{slug}', name: 'read', methods: ['GET'])]
    public function read(#[MapEntity(mapping: ['slug' => 'slug'])] Article $article): Response
    {
        return $this->render('article/read.html.twig', [
            'article' => $article,
        ]);
    }

    #[Route('/{slug}/update', name: 'update', methods: ['GET', 'POST'])]
    public function update(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Article $article, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Mise à jour du slug si le titre a été modifié
            $slug = $slugger->slug($article->getTitle())->lower();
            $article->setSlug($slug);

            $entityManager->flush();

            $this->addFlash('success', 'L\'article a été mis à jour avec succès.');

            return $this->redirectToRoute('article_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('article/update.html.twig', [
            'article' => $article,
            'form' => $form,
        ]);
    }

    #[Route('/{slug}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Article $article, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $article->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($article);
            $entityManager->flush();
            $this->addFlash('success', 'L\'article a été supprimé avec succès.');
        }

        return $this->redirectToRoute('article_index', [], Response::HTTP_SEE_OTHER);
    }
}
