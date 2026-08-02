<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Project;
use App\Entity\ProjectHistory;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Enum\ProjectStatusEnum;
use App\Enum\QuoteStatusEnum;
use App\Repository\ProjectRepository;
use App\Repository\QuoteRequestRepository;
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
    public function index(ProjectRepository $projectRepository, QuoteRequestRepository $quoteRequestRepository): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        $projects = $projectRepository->findForStakeholder($user);
        $quotes = $quoteRequestRepository->findByUser($user);

        $activeStatuses = [ProjectStatusEnum::IN_PROGRESS, ProjectStatusEnum::COLLABORATION];

        return $this->render('member/project/index.html.twig', [
            'projects' => $projects,
            'activeProjectsCount' => \count(array_filter($projects, static fn (Project $p) => \in_array($p->getStatus(), $activeStatuses, true))),
            'completedProjectsCount' => \count(array_filter($projects, static fn (Project $p) => ProjectStatusEnum::COMPLETED === $p->getStatus())),
            'pendingQuotesCount' => \count(array_filter($quotes, static fn ($q) => QuoteStatusEnum::PENDING === $q->getStatus())),
        ]);
    }

    #[Route('/{id}', name: 'read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(Project $project): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        $user = $this->getUser();
        \assert($user instanceof User);

        // Fil d'activité : on masque le bruit purement interne (tentatives d'accès refusées).
        $histories = array_filter(
            $project->getHistories()->toArray(),
            static fn (ProjectHistory $h): bool => 'access_denied' !== $h->getAction(),
        );
        usort($histories, static fn (ProjectHistory $a, ProjectHistory $b): int => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $this->render('member/project/read.html.twig', [
            'project' => $project,
            'histories' => \array_slice($histories, 0, 15),
            'isTeamMember' => $project->isTeamMember($user) || $this->isGranted('ROLE_ADMIN'),
            'canLogTime' => $this->isGranted(ProjectVoter::LOG_TIME, $project),
        ]);
    }

    #[Route('/{id}/time-entries/add', name: 'add_time_entry', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addTimeEntry(Project $project, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::LOG_TIME, $project);

        $user = $this->getUser();
        \assert($user instanceof User);

        if (!$this->isCsrfTokenValid('member_time_entry_add_'.$project->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('member_project_read', ['id' => $project->getId()]);
        }

        $hours = (float) str_replace(',', '.', (string) $request->request->get('hours'));
        $minutes = (int) round($hours * 60);

        if ($minutes <= 0) {
            $this->addFlash('error', 'Merci d\'indiquer une durée valide.');

            return $this->redirectToRoute('member_project_read', ['id' => $project->getId()]);
        }

        $entry = new TimeEntry();
        $entry->setUser($user)->setMinutes($minutes);

        $spentOn = (string) $request->request->get('spentOn');
        $date = '' !== $spentOn ? \DateTimeImmutable::createFromFormat('Y-m-d', $spentOn) : null;
        if ($date instanceof \DateTimeImmutable) {
            $entry->setSpentOn($date);
        }

        $description = trim((string) $request->request->get('description'));
        $entry->setDescription('' !== $description ? $description : null);

        $project->addTimeEntry($entry);
        $entityManager->persist($entry);
        $entityManager->flush();
        $this->addFlash('success', 'Temps enregistré.');

        return $this->redirectToRoute('member_project_read', ['id' => $project->getId()]);
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
