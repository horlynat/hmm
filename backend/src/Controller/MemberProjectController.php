<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Security\Voter\ProjectVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Espace membre (client / collaborateur) — consultation de SES projets et
 * participation à la discussion. Distinct du back-office admin (/admin) :
 * accès dès ROLE_USER, périmètre borné par ProjectVoter::VIEW.
 */
#[Route('/projects', name: 'member_project_')]
final class MemberProjectController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $this->render('member/project/index.html.twig', [
            'projects' => $projectRepository->findForStakeholder($user),
        ]);
    }

    #[Route('/{id}', name: 'read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(Project $project): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $this->render('member/project/read.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/{id}/comments/add', name: 'add_comment', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addComment(Project $project, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        $user = $this->getUser();
        \assert($user instanceof User);

        if (!$this->isCsrfTokenValid('member_comment_add_'.$project->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('member_project_read', ['id' => $project->getId()]);
        }

        $content = trim((string) $request->request->get('content'));
        if ('' !== $content) {
            $comment = new Comment();
            $comment->setContent($content)->setAuthor($user);
            $project->addComment($comment);
            $entityManager->persist($comment);
            $entityManager->flush();
            $this->addFlash('success', 'Commentaire publié.');
        }

        return $this->redirectToRoute('member_project_read', ['id' => $project->getId()]);
    }
}
