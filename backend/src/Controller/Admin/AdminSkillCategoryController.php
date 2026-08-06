<?php

namespace App\Controller\Admin;

use App\Entity\SkillCategory;
use App\Form\SkillCategoryType;
use App\Repository\SkillCategoryRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur pour la gestion des catégories de compétences dans le dashboard admin.
 *
 * 🔒 Sécurité :
 * - Accès strictement réservé au rôle ROLE_ADMIN.
 * - Validation CSRF renforcée sur l'action de suppression.
 */
#[Route('/admin/skill/category', name: 'admin_skill_category_')]
final class AdminSkillCategoryController extends AbstractController
{
    // =========================================================================
    // 📌 LISTE DES CATÉGORIES DE COMPÉTENCES
    // =========================================================================

    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(SkillCategoryRepository $skillCategoryRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/skill_category/index.html.twig', [
            'skill_categories' => $skillCategoryRepository->findAll(),
        ]);
    }

    // =========================================================================
    // 📌 CRÉATION D'UNE CATÉGORIE
    // =========================================================================

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $skillCategory = new SkillCategory();
        $form = $this->createForm(SkillCategoryType::class, $skillCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($skillCategory);
            $entityManager->flush();

            $auditLogger->log(SkillCategory::class, $skillCategory->getId(), $skillCategory->getName(), 'created');
            $entityManager->flush();

            $this->addFlash('success', 'La catégorie de compétence a été créée avec succès.');
            return $this->redirectToRoute('admin_skill_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/skill_category/create.html.twig', [
            'skill_category' => $skillCategory,
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 CONSULTATION D'UNE CATÉGORIE
    // =========================================================================

    #[Route('/{id}', name: 'read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(SkillCategory $skillCategory): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/skill_category/read.html.twig', [
            'skill_category' => $skillCategory,
        ]);
    }

    // =========================================================================
    // 📌 MISE À JOUR D'UNE CATÉGORIE
    // =========================================================================

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function update(Request $request, SkillCategory $skillCategory, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(SkillCategoryType::class, $skillCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $auditLogger->log(SkillCategory::class, $skillCategory->getId(), $skillCategory->getName(), 'updated');
            $entityManager->flush();

            $this->addFlash('success', 'La catégorie de compétence a été mise à jour avec succès.');
            return $this->redirectToRoute('admin_skill_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/skill_category/update.html.twig', [
            'skill_category' => $skillCategory,
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 SUPPRESSION D'UNE CATÉGORIE
    // =========================================================================

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, SkillCategory $skillCategory, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Validation sécurisée et uniforme du jeton CSRF
        if ($this->isCsrfTokenValid('admin_skill_category_delete_' . $skillCategory->getId(), $request->request->get('_token'))) {
            $auditLogger->log(SkillCategory::class, $skillCategory->getId(), $skillCategory->getName(), 'deleted');
            $entityManager->remove($skillCategory);
            $entityManager->flush();
            
            $this->addFlash('success', 'La catégorie de compétence a été supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Action de suppression annulée.');
        }

        return $this->redirectToRoute('admin_skill_category_index', [], Response::HTTP_SEE_OTHER);
    }
}
