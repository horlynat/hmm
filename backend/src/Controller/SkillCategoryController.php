<?php

namespace App\Controller;

use App\Entity\SkillCategory;
use App\Form\SkillCategoryType;
use App\Repository\SkillCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/skill/category', name: 'skill_category_')]
final class SkillCategoryController extends AbstractController
{
    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(SkillCategoryRepository $skillCategoryRepository): Response
    {
        return $this->render('skill_category/index.html.twig', [
            'skill_categories' => $skillCategoryRepository->findAll(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $skillCategory = new SkillCategory();
        $form = $this->createForm(SkillCategoryType::class, $skillCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($skillCategory);
            $entityManager->flush();

            return $this->redirectToRoute('skill_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('skill_category/create.html.twig', [
            'skill_category' => $skillCategory,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'read', methods: ['GET'])]
    public function read(SkillCategory $skillCategory): Response
    {
        return $this->render('skill_category/read.html.twig', [
            'skill_category' => $skillCategory,
        ]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'])]
    public function update(Request $request, SkillCategory $skillCategory, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SkillCategoryType::class, $skillCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('skill_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('skill_category/update.html.twig', [
            'skill_category' => $skillCategory,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, SkillCategory $skillCategory, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $skillCategory->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($skillCategory);
            $entityManager->flush();
        }

        return $this->redirectToRoute('skill_category_index', [], Response::HTTP_SEE_OTHER);
    }
}
