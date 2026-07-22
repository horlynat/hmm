<?php

namespace App\Controller\Admin;

use App\Entity\Experience;
use App\Form\ExperienceType;
use App\Repository\ExperienceRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/experience', name: 'admin_experience_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminExperienceController extends AbstractController
{
    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(ExperienceRepository $experienceRepository): Response
    {
        return $this->render('admin/experience/index.html.twig', [
            'experiences' => $experienceRepository->findAll(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $experience = new Experience();
        $form = $this->createForm(ExperienceType::class, $experience);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($experience);
            $entityManager->flush();

            $auditLogger->log(Experience::class, $experience->getId(), $experience->getCompany(), 'created');
            $entityManager->flush();

            $this->addFlash('success', 'L\'expérience professsionnelle a été ajoutée avec succès.');
            return $this->redirectToRoute('admin_experience_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/experience/create.html.twig', [
            'experience' => $experience,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'read', methods: ['GET'])]
    public function read(Experience $experience): Response
    {
        return $this->render('admin/experience/read.html.twig', [
            'experience' => $experience,
        ]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'])]
    public function update(Request $request, Experience $experience, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $form = $this->createForm(ExperienceType::class, $experience);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $auditLogger->log(Experience::class, $experience->getId(), $experience->getCompany(), 'updated');
            $entityManager->flush();
            $this->addFlash('success', 'L\'expérience professsionnelle a été mise à jour avec succès.');
            return $this->redirectToRoute('admin_experience_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/experience/update.html.twig', [
            'experience' => $experience,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Experience $experience, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        if ($this->isCsrfTokenValid('delete' . $experience->getId(), $request->getPayload()->getString('_token'))) {
            $auditLogger->log(Experience::class, $experience->getId(), $experience->getCompany(), 'deleted');
            $entityManager->remove($experience);
            $entityManager->flush();
        }
        $this->addFlash('danger', 'L\'expérience professsionnelle a été supprimée avec succès.');
        return $this->redirectToRoute('admin_experience_index', [], Response::HTTP_SEE_OTHER);
    }
}
