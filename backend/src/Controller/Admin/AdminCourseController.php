<?php

namespace App\Controller\Admin;

use App\Entity\Course;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur pour la gestion des cours du dashboard admin.
 *
 * 🔒 Sécurité :
 * - Accès exclusif aux comptes dotés du rôle ROLE_ADMIN.
 * - Validation stricte des jetons CSRF pour éviter les suppressions malveillantes.
 */
#[Route('/admin/course', name: 'admin_course_')]
final class AdminCourseController extends AbstractController
{
    // =========================================================================
    // 📌 LISTE DES COURS
    // =========================================================================

    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(CourseRepository $courseRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/course/index.html.twig', [
            'courses' => $courseRepository->findAll(),
        ]);
    }

    // =========================================================================
    // 📌 CRÉATION D'UN COURS
    // =========================================================================

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($course);
            $entityManager->flush();

            $this->addFlash('success', 'Le cours a été créé avec succès.');
            return $this->redirectToRoute('admin_course_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/course/create.html.twig', [
            'course' => $course,
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 CONSULTATION D'UN COURS
    // =========================================================================

    #[Route('/{id}', name: 'read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(Course $course): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/course/read.html.twig', [
            'course' => $course,
        ]);
    }

    // =========================================================================
    // 📌 MISE À JOUR D'UN COURS
    // =========================================================================

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Course $course, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le cours a été mis à jour avec succès.');
            return $this->redirectToRoute('admin_course_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/course/update.html.twig', [
            'course' => $course,
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 SUPPRESSION D'UN COURS
    // =========================================================================

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Course $course, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // ✅ Correction : Le message de succès est désormais conditionné par la validité du token
        if ($this->isCsrfTokenValid('admin_course_delete_' . $course->getId(), $request->request->get('_token'))) {
            $entityManager->remove($course);
            $entityManager->flush();
            
            $this->addFlash('success', 'Le cours a été supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Action de suppression annulée.');
        }

        return $this->redirectToRoute('admin_course_index', [], Response::HTTP_SEE_OTHER);
    }
}