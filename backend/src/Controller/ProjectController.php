<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Media;
use App\Form\ProjectType;
use App\Repository\ProjectRepository;
use App\Service\MediaUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
// use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Contrôleur pour la gestion des projets dans le tableau de bord.
 * Permet de créer, lire, mettre à jour et supprimer des projets, ainsi que d'ajouter des médias (images et documents).
 */
#[Route('/dashboard/project', name: 'project_')]
final class ProjectController extends AbstractController
{
    /**
     * Affiche la liste des projets.
     *
     * @param ProjectRepository $projectRepository Repository pour récupérer les projets
     * @return Response Réponse HTTP avec la liste des projets
     */
    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository): Response
    {
        return $this->render('project/index.html.twig', [
            'projects' => $projectRepository->findAll(),
        ]);
    }

    /**
     * Crée un nouveau projet avec la possibilité d'ajouter des médias (images et documents).
     *
     * @param Request $request Requête HTTP actuelle
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine
     * @param SluggerInterface $slugger Service pour générer des slugs
     * @param MediaUploader $mediaUploader Service pour uploader les médias
     * @return Response Réponse HTTP (redirection ou rendu du formulaire)
     */
    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        MediaUploader $mediaUploader
    ): Response {
        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Génération automatique du slug
            $slug = $slugger->slug($project->getTitle())->lower();
            $project->setSlug($slug);

            // Gestion des fichiers (images et documents)
            $mediaFiles = $form->get('media')->getData();

            if ($mediaFiles && count($mediaFiles) > 0) {
                // ✅ Upload multiple via le service
                $results = $mediaUploader->uploadMultiple($mediaFiles, 'projects');

                foreach ($results as $result) {
                    $media = new Media();
                    $media->setFilePath($result['path']);
                    $media->setAltText($project->getTitle() ?? 'Project Media');
                    $media->setMimeType($result['mimeType']);
                    $media->setSize($result['size']);
                    $media->setType($result['type']); // déjà calculé par le service
                    $media->setUploadedAt($result['uploadedAt']);

                    $entityManager->persist($media);
                    $project->addMedia($media);
                }
            }

            $entityManager->persist($project);
            $entityManager->flush();

            $this->addFlash('success', 'Le projet a été créé avec succès.');
            return $this->redirectToRoute('project_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('project/create.html.twig', [
            'project' => $project,
            'form' => $form->createView(),
        ]);
    }


    /**
     * Affiche les détails d'un projet.
     *
     * @param Project $project Projet à afficher
     * @return Response Réponse HTTP avec les détails du projet
     */
    #[Route('/{id}', name: 'read', methods: ['GET'])]
    public function read(Project $project): Response
    {
        return $this->render('project/read.html.twig', [
            'project' => $project,
        ]);
    }

    /**
     * Met à jour un projet existant et permet d'ajouter de nouveaux médias.
     *
     * @param Request $request Requête HTTP actuelle
     * @param Project $project Projet à mettre à jour
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine
     * @param SluggerInterface $slugger Service pour générer des slugs
     * @param MediaUploader $mediaUploader Service pour uploader les médias
     * @return Response Réponse HTTP (redirection ou rendu du formulaire)
     */
   #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'])]
    public function update(
        Request $request,
        Project $project,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        MediaUploader $mediaUploader
    ): Response {
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Mise à jour du slug
            $slug = $slugger->slug($project->getTitle())->lower();
            $project->setSlug($slug);

            // Gestion des nouveaux fichiers (images et documents)
            $mediaFiles = $form->get('media')->getData();
            if ($mediaFiles && count($mediaFiles) > 0) {
                // ✅ Upload multiple via le service
                $results = $mediaUploader->uploadMultiple($mediaFiles, 'projects');

                foreach ($results as $result) {
                    $media = new Media();
                    $media->setFilePath($result['path']);
                    $media->setAltText($project->getTitle() ?? 'Project Media');
                    $media->setMimeType($result['mimeType']);
                    $media->setSize($result['size']);
                    $media->setType($result['type']); // déjà calculé par le service
                    $media->setUploadedAt($result['uploadedAt']);

                    $entityManager->persist($media);
                    $project->addMedia($media);
                }
            }

            // Mise à jour du timestamp
            // $project->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Le projet a été mis à jour avec succès.');
            return $this->redirectToRoute('project_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('project/update.html.twig', [
            'project' => $project,
            'form' => $form->createView(),
        ]);
    }


    /**
     * Supprime un projet.
     * Vérifie le token CSRF pour éviter les attaques.
     *
     * @param Request $request Requête HTTP actuelle
     * @param Project $project Projet à supprimer
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine
     * @return Response Réponse HTTP (redirection)
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Project $project,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $project->getId(), $request->request->get('_token'))) {
            $entityManager->remove($project);
            $entityManager->flush();
            $this->addFlash('success', 'Le projet a été supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');
        }

        return $this->redirectToRoute('project_index', [], Response::HTTP_SEE_OTHER);
    }
}
