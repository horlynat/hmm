<?php

namespace App\Controller;

use App\Entity\Course;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/course', name: 'course_')]
final class CourseController extends AbstractController
{
    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(CourseRepository $courseRepository): Response
    {
        return $this->render('course/index.html.twig', [
            'courses' => $courseRepository->findAll(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($course);
            $entityManager->flush();
            $this->addFlash('success', 'Le cours a été créé avec succès.');
            return $this->redirectToRoute('course_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/create.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'read', methods: ['GET'])]
    public function read(Course $course): Response
    {
        return $this->render('course/read.html.twig', [
            'course' => $course,
        ]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'])]
    public function update(Request $request, Course $course, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le cours a été mis à jour avec succès.');
            return $this->redirectToRoute('course_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/update.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Course $course, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $course->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($course);
            $entityManager->flush();
        }
        $this->addFlash('danger', 'Le cours a été supprimé avec succès.');
        return $this->redirectToRoute('course_index', [], Response::HTTP_SEE_OTHER);
    }
}
