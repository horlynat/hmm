<?php

namespace App\Controller\Admin;

use App\Entity\Skill;
use App\Form\SkillType;
use App\Repository\SkillRepository;
use App\Security\Voter\SkillVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur pour la gestion des compétences (Skills) dans le dashboard admin.
 *
 * 🔒 Sécurité :
 * - Réservé exclusivement aux administrateurs (ROLE_ADMIN).
 * - Validation CSRF requise sur les suppressions.
 */
#[Route('/admin/skill', name: 'admin_skill_')]
final class AdminSkillController extends AbstractController
{
    // =========================================================================
    // 📌 LISTE DES COMPÉTENCES
    // =========================================================================

    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(SkillRepository $skillRepository): Response
    {
        // Pas d'attribut SKILL_VIEW dédié : lecture ouverte à qui peut créer/éditer.
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        return $this->render('admin/skill/index.html.twig', [
            'skills' => $skillRepository->findAll(),
        ]);
    }

    // =========================================================================
    // 📌 CRÉATION D'UNE COMPÉTENCE
    // =========================================================================

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(SkillVoter::CREATE);

        $skill = new Skill();
        $form = $this->createForm(SkillType::class, $skill);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($skill);
            $entityManager->flush();

            $auditLogger->log(Skill::class, $skill->getId(), $skill->getName(), 'created');
            $entityManager->flush();

            $this->addFlash('success', 'La compétence a été créée avec succès.');
            return $this->redirectToRoute('admin_skill_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/skill/create.html.twig', [
            'skill' => $skill,
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 CONSULTATION D'UNE COMPÉTENCE
    // =========================================================================

    #[Route('/{id}', name: 'read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(Skill $skill): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        return $this->render('admin/skill/read.html.twig', [
            'skill' => $skill,
        ]);
    }

    // =========================================================================
    // 📌 MISE À JOUR D'UNE COMPÉTENCE
    // =========================================================================

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Skill $skill, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(SkillVoter::EDIT, $skill);

        $form = $this->createForm(SkillType::class, $skill);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $auditLogger->log(Skill::class, $skill->getId(), $skill->getName(), 'updated');
            $entityManager->flush();

            $this->addFlash('success', 'La compétence a été mise à jour avec succès.');
            return $this->redirectToRoute('admin_skill_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/skill/update.html.twig', [
            'skill' => $skill,
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 SUPPRESSION D'UNE COMPÉTENCE
    // =========================================================================

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Skill $skill, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(SkillVoter::DELETE, $skill);

        // Uniformisation du jeton de sécurité avec le préfixe de l'application
        if ($this->isCsrfTokenValid('admin_skill_delete_' . $skill->getId(), $request->request->get('_token'))) {
            $auditLogger->log(Skill::class, $skill->getId(), $skill->getName(), 'deleted');
            $entityManager->remove($skill);
            $entityManager->flush();
            
            $this->addFlash('success', 'La compétence a été supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Action de suppression annulée.');
        }

        return $this->redirectToRoute('admin_skill_index', [], Response::HTTP_SEE_OTHER);
    }
}