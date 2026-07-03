<?php

namespace App\Controller;

use App\Entity\Skill;
use App\Form\SkillType;
use App\Repository\SkillRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/skill', name: 'skill_')]
final class SkillController extends AbstractController
{
    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(SkillRepository $skillRepository): Response
    {
        return $this->render('skill/index.html.twig', [
            'skills' => $skillRepository->findAll(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $skill = new Skill();
        $form = $this->createForm(SkillType::class, $skill);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($skill);
            $entityManager->flush();

            return $this->redirectToRoute('skill_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('skill/create.html.twig', [
            'skill' => $skill,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'read', methods: ['GET'])]
    public function read(Skill $skill): Response
    {
        return $this->render('skill/read.html.twig', [
            'skill' => $skill,
        ]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'])]
    public function update(Request $request, Skill $skill, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SkillType::class, $skill);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('skill_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('skill/update.html.twig', [
            'skill' => $skill,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Skill $skill, EntityManagerInterface $entityManager): Response
    {
        // ✅ Utilisez $request->request pour les formulaires HTML (pas getPayload() !)
        if ($this->isCsrfTokenValid('delete' . $skill->getId(), $request->request->get('_token'))) {
            $entityManager->remove($skill);
            $entityManager->flush();
            $this->addFlash('success', 'La compétence a été supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('skill_index', [], Response::HTTP_SEE_OTHER);
    }
}
