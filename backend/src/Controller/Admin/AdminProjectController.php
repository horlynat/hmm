<?php

namespace App\Controller\Admin;

use App\Entity\Comment;
use App\Entity\Media;
use App\Entity\Project;
use App\Entity\ProjectExpense;
use App\Entity\ProjectInfo;
use App\Entity\ProjectTask;
use App\Entity\Tag;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Enum\BillingTypeEnum;
use App\Enum\TaskStatusEnum;
use App\Enum\BudgetStatusEnum;
use App\Enum\ProjectPriorityEnum;
use App\Enum\ProjectStatusEnum;
use App\Form\ProjectExpenseType;
use App\Form\ProjectType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Repository\ProjectHistoryRepository;
use App\Repository\ProjectRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\MediaUploader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Contrôleur pour la gestion des projets dans le tableau de bord admin.
 *
 * 🔒 Sécurité :
 * - Chaque action est protégée par le ProjectVoter (VIEW, EDIT, DELETE).
 * - Double vérification des statuts (COMPLETED, SUSPENDED) pour éviter les modifications non autorisées.
 * - Vérification des tokens CSRF pour les actions sensibles (suppression).
 *
 * ✅ Fonctionnalités intégrées :
 * - Toi (owner) crées et administres chaque projet ; le champ "client" identifie
 *   la personne à qui le projet est confié (visible en lecture pour elle).
 * - Journalisation complète des actions dans ProjectHistory.
 * - Gestion des médias (upload, suppression).
 * - Pagination et filtres avancés.
 * - Contrôles explicites des rôles (ROLE_ADMIN) et du ProjectVoter (VIEW/EDIT/DELETE).
 */
#[Route('/admin/project', name: 'admin_project_')]
final class AdminProjectController extends AbstractController
{
    // =========================================================================
    // 📌 LISTE DES PROJETS (AVEC PAGINATION ET FILTRES)
    // =========================================================================

    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        ProjectRepository $projectRepository,
        UserRepository $userRepository,
        TagRepository $tagRepository,
        ProjectHistoryRepository $projectHistoryRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // 📌 Initialisation de TOUS les filtres possibles avec des valeurs par défaut
        $defaultFilters = [
            'status' => $request->query->get('status', ''),
            'search' => $request->query->get('search', ''),
            'budget_status' => $request->query->get('budget_status', ''),
            'client' => $request->query->get('client', ''),
            'collaborator' => $request->query->get('collaborator', ''),
            'budget_min' => null !== $request->query->get('budget_min') && '' !== $request->query->get('budget_min') ? (float) $request->query->get('budget_min') : null,
            'budget_max' => null !== $request->query->get('budget_max') && '' !== $request->query->get('budget_max') ? (float) $request->query->get('budget_max') : null,
            'date_start' => $request->query->get('date_start', ''),
            'date_end' => $request->query->get('date_end', ''),
            'time_urgency' => $request->query->get('time_urgency', ''),
            'inactive_days' => null !== $request->query->get('inactive_days') && '' !== $request->query->get('inactive_days') ? (int) $request->query->get('inactive_days') : null,
            'billing_type' => $request->query->get('billing_type', ''),
            'priority' => $request->query->get('priority', ''),
            'tag' => $request->query->get('tag', ''),
            'team_size' => $request->query->get('team_size', ''),
            'orphan' => $request->query->get('orphan', ''),
            'sort' => $request->query->get('sort', 'createdAt'),
            'direction' => $request->query->get('direction', 'desc'),
            'progress_min' => null !== $request->query->get('progress_min') && '' !== $request->query->get('progress_min') ? (int) $request->query->get('progress_min') : null,
            'progress_max' => null !== $request->query->get('progress_max') && '' !== $request->query->get('progress_max') ? (int) $request->query->get('progress_max') : null,
        ];

        // Récupération des variables utiles pour la suite du contrôleur
        $page = max(1, $request->query->getInt('page', 1));
        $sort = $defaultFilters['sort'];
        $direction = $defaultFilters['direction'];
        $limit = 20; // Nombre de projets par page

        // 📌 Construire la requête de base avec les jointures nécessaires
        $queryBuilder = $projectRepository->createQueryBuilder('p')
            ->leftJoin('p.client', 'cl')
            ->leftJoin('p.collaborators', 'c')
            ->leftJoin('p.tags', 't');

        // 📌 Appliquer les filtres
        $this->applyFilters($queryBuilder, $defaultFilters);

        // 📌 Appliquer le tri de manière sécurisée
        $sortField = in_array($sort, ['title', 'budget', 'progress', 'deadline', 'createdAt'], true) ? $sort : 'createdAt';
        $sortDirection = 'ASC' === strtoupper($direction) ? 'ASC' : 'DESC';
        $queryBuilder->orderBy('p.'.$sortField, $sortDirection);

        // 📌 Pagination
        $paginator = new Paginator($queryBuilder);
        $totalItems = $paginator->count();
        $totalPages = (int) ceil($totalItems / $limit);

        $projects = $paginator
            ->getQuery()
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getResult();

        // 📌 Statistiques globales
        $stats = Project::getBudgetStatistics($entityManager);

        // 📌 Liste des utilisateurs et tags (pour les filtres latéraux)
        $users = $userRepository->findAll();
        $tags = $tagRepository->findAll();

        // 📌 Projets par statut (pour les cartes de statistiques) recalculés par rapport aux filtres courants
        $projectsByStatus = [];
        foreach (ProjectStatusEnum::cases() as $status) {
            $statusQueryBuilder = $projectRepository->createQueryBuilder('p')
                ->select('COUNT(DISTINCT p.id)')
                ->leftJoin('p.client', 'cl')
                ->leftJoin('p.collaborators', 'c')
                ->leftJoin('p.tags', 't');

            $statusFilters = array_merge($defaultFilters, ['status' => $status->value]);
            $this->applyFilters($statusQueryBuilder, $statusFilters);

            $projectsByStatus[$status->value] = [
                'label' => $status->getLabel(),
                'count' => (int) $statusQueryBuilder->getQuery()->getSingleScalarResult(),
                'badgeClass' => $status->getBadgeClass(),
            ];
        }

        // 📌 Activité récente tous projets confondus (couvre déjà les dépenses via
        // l'action 'expense_added', pas besoin d'une requête séparée sur ProjectExpense)
        $recentHistory = $projectHistoryRepository->findRecent(10);

        // 📌 Agrégats de la centrale de gestion (portefeuille entier, indépendant des filtres)
        $portfolio = $projectRepository->getCommandCenterStats();
        $pendingApprovals = $projectRepository->getPendingApprovalsSummary();
        $byPriority = $projectRepository->countByPriority();
        $upcomingDeadlines = $projectRepository->findUpcomingDeadlines(14, 6);
        $workload = $projectRepository->countActiveProjectsByCollaborator(5);

        return $this->render('admin/project/projects.html.twig', [
            'projects' => $projects,
            'users' => $users,
            'tags' => $tags,
            'stats' => $stats,
            'statuses' => ProjectStatusEnum::cases(),
            'priorities' => ProjectPriorityEnum::cases(),
            'billingTypes' => BillingTypeEnum::cases(),
            'budgetStatuses' => BudgetStatusEnum::cases(),
            'projectsByStatus' => $projectsByStatus,
            'recentHistory' => $recentHistory,
            'portfolio' => $portfolio,
            'pendingApprovals' => $pendingApprovals,
            'byPriority' => $byPriority,
            'upcomingDeadlines' => $upcomingDeadlines,
            'workload' => $workload,
            'filters' => $defaultFilters,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'limit' => $limit,
        ]);
    }

    // =========================================================================
    // 📌 APPLY FILTERS (MÉTHODE PRIVÉE POUR CENTRALISER LA LOGIQUE)
    // =========================================================================

    /**
     * Applique les filtres à la requête Doctrine.
     *
     * @param array<string, mixed> $filters
     */
    private function applyFilters(QueryBuilder $queryBuilder, array $filters): void
    {
        // 1. Recherche par statut
        if (!empty($filters['status'])) {
            $queryBuilder->andWhere('p.status = :status')
                ->setParameter('status', $filters['status']);
        }

        // 2. Recherche par état du budget (Santé financière)
        if (!empty($filters['budget_status'])) {
            match ($filters['budget_status']) {
                'over' => $queryBuilder->andWhere('p.spent > p.budget'),
                'low' => $queryBuilder->andWhere('p.budget > 0 AND (p.budget - p.spent) / p.budget < 0.1'),
                'ok' => $queryBuilder->andWhere('p.spent <= p.budget AND (p.budget = 0 OR (p.budget - p.spent) / p.budget >= 0.1)'),
                default => null,
            };
        }

        // 3. Recherche par client
        if (!empty($filters['client'])) {
            $queryBuilder->andWhere('cl.id = :client')
                ->setParameter('client', $filters['client']);
        }

        // 4. Recherche par collaborateur
        if (!empty($filters['collaborator'])) {
            $queryBuilder->andWhere('c.id = :collaborator')
                ->setParameter('collaborator', $filters['collaborator']);
        }

        // 5. Filtre par recherche (titre, description)
        if (!empty($filters['search'])) {
            $queryBuilder->andWhere('p.title LIKE :search OR p.description LIKE :search')
                ->setParameter('search', '%'.$filters['search'].'%');
        }

        // 6. Type de facturation
        if (!empty($filters['billing_type'])) {
            $queryBuilder->andWhere('p.billingType = :billing_type')
                ->setParameter('billing_type', $filters['billing_type']);
        }

        // 7. Niveau de Priorité
        if (!empty($filters['priority'])) {
            $queryBuilder->andWhere('p.priority = :priority')
                ->setParameter('priority', $filters['priority']);
        }

        // 8. Technologie / Tag associé
        if (!empty($filters['tag'])) {
            $queryBuilder->andWhere('t.id = :tag')
                ->setParameter('tag', $filters['tag']);
        }

        // 9. Date minimale de création (Planning)
        if (!empty($filters['date_start'])) {
            $queryBuilder->andWhere('p.createdAt >= :date_start')
                ->setParameter('date_start', new \DateTime($filters['date_start']));
        }

        // 10. Date maximale de création (Planning)
        if (!empty($filters['date_end'])) {
            $queryBuilder->andWhere('p.createdAt <= :date_end')
                ->setParameter('date_end', new \DateTime($filters['date_end'].' 23:59:59'));
        }

        // 11. Budget Minimum
        if (null !== $filters['budget_min']) {
            $queryBuilder->andWhere('p.budget >= :budget_min')
                ->setParameter('budget_min', $filters['budget_min']);
        }

        // 12. Budget Maximum
        if (null !== $filters['budget_max']) {
            $queryBuilder->andWhere('p.budget <= :budget_max')
                ->setParameter('budget_max', $filters['budget_max']);
        }

        // 13. Seuils d'avancement minimum (%)
        if (null !== $filters['progress_min']) {
            $queryBuilder->andWhere('p.progress >= :progress_min')
                ->setParameter('progress_min', $filters['progress_min']);
        }

        // 14. Seuils d'avancement maximum (%)
        if (null !== $filters['progress_max']) {
            $queryBuilder->andWhere('p.progress <= :progress_max')
                ->setParameter('progress_max', $filters['progress_max']);
        }

        // 15. Urgence Échéances (overdue / imminent)
        if (!empty($filters['time_urgency'])) {
            $now = new \DateTime();
            $inSevenDays = (new \DateTime())->modify('+7 days');

            match ($filters['time_urgency']) {
                'overdue' => $queryBuilder->andWhere('p.deadline < :now AND p.status != :completedStatus')
                    ->setParameter('now', $now)
                    ->setParameter('completedStatus', ProjectStatusEnum::COMPLETED->value),
                'imminent' => $queryBuilder->andWhere('p.deadline BETWEEN :now AND :inSevenDays AND p.status != :completedStatus')
                    ->setParameter('now', $now)
                    ->setParameter('inSevenDays', $inSevenDays)
                    ->setParameter('completedStatus', ProjectStatusEnum::COMPLETED->value),
                default => null,
            };
        }

        // 16. Jours d'inactivité (Dormant)
        if (null !== $filters['inactive_days']) {
            $targetDate = (new \DateTime())->modify(sprintf('-%d days', $filters['inactive_days']));
            $queryBuilder->andWhere('p.updatedAt <= :targetDate')
                ->setParameter('targetDate', $targetDate);
        }

        // 17. Taille de l'équipe affectée (solo / small / large)
        if (!empty($filters['team_size'])) {
            match ($filters['team_size']) {
                'solo' => $queryBuilder->andWhere('c.id IS NULL'),
                'small' => $queryBuilder->andHaving('COUNT(DISTINCT c.id) BETWEEN 1 AND 3')->groupBy('p.id'),
                'large' => $queryBuilder->andHaving('COUNT(DISTINCT c.id) > 3')->groupBy('p.id'),
                default => null,
            };
        }

        // 18. Anomalies de structure / Orphelin
        if (!empty($filters['orphan'])) {
            match ($filters['orphan']) {
                'no_client' => $queryBuilder->andWhere('p.client IS NULL'),
                'no_team' => $queryBuilder->andWhere('c.id IS NULL'),
                default => null,
            };
        }
    }

    // =========================================================================
    // 📌 STATISTIQUES GLOBALES
    // =========================================================================

    #[Route('/statistics', name: 'statistics', methods: ['GET'])]
    public function statistics(
        EntityManagerInterface $entityManager,
        ProjectRepository $projectRepository,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $stats = Project::getBudgetStatistics($entityManager);

        // Récupérer les projets en dépassement de budget
        $overBudgetProjects = $projectRepository->createQueryBuilder('p')
            ->where('p.spent > p.budget')
            ->getQuery()
            ->getResult();

        // Récupérer les projets avec un budget restant faible
        $lowBudgetProjects = $projectRepository->createQueryBuilder('p')
            ->where('p.budget > 0 AND (p.budget - p.spent) / p.budget < 0.1')
            ->getQuery()
            ->getResult();

        return $this->render('admin/project/statistics.html.twig', [
            'stats' => $stats,
            'overBudgetProjects' => $overBudgetProjects,
            'lowBudgetProjects' => $lowBudgetProjects,
        ]);
    }

    // =========================================================================
    // 📌 ESPACE FREELANCE — vue d'ensemble du pool en libre-service
    // =========================================================================

    /**
     * Pendant admin de l'espace « Projets disponibles » du frontend freelance
     * (MeController::readAvailableProjects/joinProject) : contrairement au
     * badge/bannière posés sur chaque fiche projet, cette page est LE point
     * d'entrée dédié — un projet "à venir" reste malgré tout visible ici même
     * quand aucun freelance n'a encore rien rejoint, et la page reste
     * consultable même quand le pool est totalement vide (l'admin doit
     * pouvoir constater "il n'y a rien à voir", pas seulement deviner
     * l'existence de la fonctionnalité en tombant sur un projet qui l'a).
     */
    #[Route('/espace-freelance', name: 'freelance_space', methods: ['GET'])]
    public function freelanceSpace(ProjectRepository $projectRepository, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $projects = $projectRepository->findByStatus(ProjectStatusEnum::UPCOMING);

        $eligibleFreelances = array_values(array_filter(
            $userRepository->findCollaborators(),
            static fn (User $user): bool => $user->isFreelanceProfileComplete(),
        ));

        $totalAssociations = array_sum(array_map(
            static fn (Project $project): int => $project->getCollaborators()->count(),
            $projects,
        ));

        return $this->render('admin/project/freelance_space.html.twig', [
            'projects' => $projects,
            'eligibleFreelances' => $eligibleFreelances,
            'totalAssociations' => $totalAssociations,
        ]);
    }

    // =========================================================================
    // 📌 CRÉATION D'UN PROJET
    // =========================================================================

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        MediaUploader $mediaUploader,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $project = new Project();
        $project->setOwner($this->getAuthenticatedUser());

        $form = $this->createForm(ProjectType::class, $project, [
            'include_planning' => true,
            'include_showcase' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Générer le slug
            $slug = $slugger->slug($project->getTitle())->lower();
            $project->setSlug($slug);

            // Upload des médias
            $this->handleMediaUpload($project, $form, $entityManager, $mediaUploader);

            // Contenu vitrine (étape 2 de l'assistant) — ProjectInfo n'est créé
            // que si l'admin a réellement renseigné au moins un champ.
            $showcase = $this->buildProjectInfoFromForm($form);
            if ($showcase !== null) {
                $project->setInfo($showcase);
            }

            // Journaliser la création
            $project->logCreation($this->getAuthenticatedUser());

            $entityManager->persist($project);
            $entityManager->flush();

            $this->addFlash('success', 'Le projet a été créé avec succès.');

            return $this->redirectToRoute('admin_project_index');
        }

        return $this->render('admin/project/create.html.twig', [
            'project' => $project,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Construit le ProjectInfo (contenu vitrine public) à partir des champs non
     * mappés de l'étape 2 de l'assistant de création. Les textareas utilisent un
     * mini-format "un par ligne" (et "clé | valeur" pour les listes à deux
     * colonnes) plutôt que des CollectionType JS, pour rester simple à remplir.
     */
    private function buildProjectInfoFromForm(FormInterface $form): ?ProjectInfo
    {
        $role = trim((string) $form->get('role')->getData());
        $repoUrl = trim((string) $form->get('repoUrl')->getData());
        $objectives = $this->parseLines((string) $form->get('objectives')->getData());
        $techStack = $this->parsePairs((string) $form->get('techStack')->getData(), 'name', 'rationale');
        $challenges = $this->parsePairs((string) $form->get('challenges')->getData(), 'problem', 'solution');
        $results = $this->parsePairs((string) $form->get('results')->getData(), 'label', 'value');

        if ($role === '' && $repoUrl === '' && !$objectives && !$techStack && !$challenges && !$results) {
            return null;
        }

        // Champs anglais — optionnels, mêmes mini-formats que leurs pendants FR.
        $roleEn = trim((string) $form->get('roleEn')->getData());
        $objectivesEn = $this->parseLines((string) $form->get('objectivesEn')->getData());
        $techStackEn = $this->parsePairs((string) $form->get('techStackEn')->getData(), 'name', 'rationale');
        $challengesEn = $this->parsePairs((string) $form->get('challengesEn')->getData(), 'problem', 'solution');
        $resultsEn = $this->parsePairs((string) $form->get('resultsEn')->getData(), 'label', 'value');

        $info = new ProjectInfo();
        $info->setRole($role !== '' ? $role : null);
        $info->setRepoUrl($repoUrl !== '' ? $repoUrl : null);
        $info->setObjectives($objectives);
        $info->setTechStack($techStack);
        $info->setChallenges($challenges);
        $info->setResults($results);
        $info->setRoleEn($roleEn !== '' ? $roleEn : null);
        $info->setObjectivesEn($objectivesEn ?: null);
        $info->setTechStackEn($techStackEn ?: null);
        $info->setChallengesEn($challengesEn ?: null);
        $info->setResultsEn($resultsEn ?: null);

        return $info;
    }

    /** @return string[] */
    private function parseLines(string $raw): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $raw) ?: [])));
    }

    /** @return array<int, array{0: string, 1: string|null}|array<string, string|null>> */
    private function parsePairs(string $raw, string $keyA, string $keyB): array
    {
        $items = [];
        foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$a, $b] = array_pad(explode('|', $line, 2), 2, null);
            $a = trim((string) $a);
            if ($a === '') {
                continue;
            }
            $b = $b !== null ? trim($b) : '';
            $items[] = [$keyA => $a, $keyB => $b !== '' ? $b : null];
        }

        return $items;
    }

    // =========================================================================
    // 📌 DÉTAILS D'UN PROJET
    // =========================================================================

    #[Route('/{id}', name: 'read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(Project $project): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $this->render('admin/project/read.html.twig', [
            'project' => $project,
            'statuses' => ProjectStatusEnum::cases(),
            'priorities' => ProjectPriorityEnum::cases(),
            'billingTypes' => BillingTypeEnum::cases(),
            'taskStatuses' => TaskStatusEnum::cases(),
        ]);
    }

    // =========================================================================
    // 📌 MISE À JOUR D'UN PROJET
    // =========================================================================

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function update(
        Project $project,
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        MediaUploader $mediaUploader,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        // Double sécurité : empêche la modification des projets terminés ou suspendus
        if ($this->isProjectLocked($project)) {
            $this->addFlash('error', 'Ce projet ne peut plus être modifié (statut : '.$project->getStatusLabel().').');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        // Sauvegarde des anciennes valeurs pour la journalisation
        $oldStatus = $project->getStatus();
        $oldBudget = $project->getBudget();
        $oldSpent = $project->getSpent();

        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Mettre à jour le slug
            $slug = $slugger->slug($project->getTitle())->lower();
            $project->setSlug($slug);

            // Upload des nouveaux médias
            $this->handleMediaUpload($project, $form, $entityManager, $mediaUploader);

            // Journaliser les modifications
            $this->logProjectChanges($project, $oldStatus, $oldBudget, $oldSpent);

            $entityManager->flush();
            $this->addFlash('success', 'Le projet a été mis à jour avec succès.');

            return $this->redirectToRoute('admin_project_index');
        }

        return $this->render('admin/project/update.html.twig', [
            'project' => $project,
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 SUPPRESSION D'UN MÉDIA
    // =========================================================================

    #[Route('/{id}/media/{mediaId}/delete', name: 'delete_media', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteMedia(
        Project $project,
        #[MapEntity(id: 'mediaId')] Media $media,
        EntityManagerInterface $entityManager,
        Request $request,
        MediaUploader $mediaUploader,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        if ($this->isCsrfTokenValid('admin_project_delete_media_'.$media->getId(), $request->request->get('_token'))) {
            // Supprimer le fichier physique
            $mediaUploader->delete(basename($media->getFilePath()), 'projects');

            $project->removeMedia($media);
            $project->addToHistory(
                'media_removed',
                $this->getAuthenticatedUser(),
                sprintf('Média supprimé : %s', basename($media->getFilePath())),
            );
            $entityManager->remove($media);
            $entityManager->flush();

            $this->addFlash('success', 'Média supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
    }

    // =========================================================================
    // 📌 SUPPRESSION D'UN PROJET
    // =========================================================================

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        Project $project,
        EntityManagerInterface $entityManager,
        Request $request,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::DELETE, $project);

        if ($this->isCsrfTokenValid('admin_project_delete_'.$project->getId(), $request->request->get('_token'))) {
            // Journaliser la suppression (avant la suppression pour conserver l'accès)
            $project->addToHistory('project_deleted', $this->getAuthenticatedUser(), 'Projet supprimé');

            $entityManager->remove($project);
            $entityManager->flush();

            $this->addFlash('success', 'Le projet a été supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_project_index');
    }

    // =========================================================================
    // 📌 CHANGEMENT DE STATUT
    // =========================================================================

    #[Route('/{id}/status/{status}', name: 'change_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function changeStatus(
        Project $project,
        string $status,
        EntityManagerInterface $entityManager,
        Request $request,
        \App\Service\ProjectNotifier $projectNotifier,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::CHANGE_STATUS, $project);

        if (!$this->isCsrfTokenValid('change_status_'.$project->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        try {
            $newStatus = ProjectStatusEnum::from($status);
            $oldStatus = $project->getStatus();

            // Vérification de la transition de statut
            if (!$oldStatus->canTransitionTo($newStatus)) {
                $this->addFlash('error', sprintf(
                    'Transition invalide de "%s" à "%s".',
                    $oldStatus->getLabel(),
                    $newStatus->getLabel()
                ));

                return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
            }

            // Garde-fou de clôture : on ne termine pas un projet tant qu'il reste des
            // dépenses en attente d'approbation ou des tâches non terminées.
            if (ProjectStatusEnum::COMPLETED === $newStatus) {
                if ($project->hasPendingExpenses()) {
                    $this->addFlash('error', 'Impossible de clôturer : des dépenses sont encore en attente d\'approbation.');

                    return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
                }
                if ($project->hasOpenTasks()) {
                    $this->addFlash('error', sprintf(
                        'Impossible de clôturer : %d tâche(s) ne sont pas terminées.',
                        $project->getTasksCount() - $project->getDoneTasksCount(),
                    ));

                    return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
                }
            }

            // Journaliser le changement de statut
            $project->logStatusChange(
                $this->getAuthenticatedUser(),
                $oldStatus->getLabel(),
                $newStatus->getLabel()
            );

            $project->setStatus($newStatus);
            $entityManager->flush();

            $projectNotifier->statusChanged($project, $oldStatus->getLabel(), $newStatus->getLabel());

            $this->addFlash('success', sprintf(
                'Statut mis à jour : "%s" → "%s".',
                $oldStatus->getLabel(),
                $newStatus->getLabel()
            ));
        } catch (\ValueError) {
            $this->addFlash('error', 'Statut invalide.');
        }

        return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
    }

    // =========================================================================
    // 📌 PARAMÈTRES & PLANNING (édition inline depuis le centre de commande)
    // =========================================================================

    /**
     * Met à jour, « bloc par bloc », les métadonnées du projet éditables depuis la
     * page de lecture (priorité, facturation, planning). Chaque champ n'est modifié
     * que s'il est présent dans la requête : un mini-formulaire peut donc n'envoyer
     * qu'une partie des champs. Une valeur vide efface le champ correspondant.
     */
    #[Route('/{id}/settings', name: 'settings', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateSettings(
        Project $project,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        if (!$this->isCsrfTokenValid('project_settings_'.$project->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        if ($this->isProjectLocked($project)) {
            $this->addFlash('error', 'Ce projet ne peut plus être modifié (statut : '.$project->getStatusLabel().').');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        $updated = [];

        if ($request->request->has('priority')) {
            $value = (string) $request->request->get('priority');
            $project->setPriority('' !== $value ? ProjectPriorityEnum::tryFrom($value) : null);
            $updated[] = 'priorité';
        }

        if ($request->request->has('billingType')) {
            $value = (string) $request->request->get('billingType');
            $project->setBillingType('' !== $value ? BillingTypeEnum::tryFrom($value) : null);
            $updated[] = 'facturation';
        }

        $dateFields = ['startedAt' => 'setStartedAt', 'deadline' => 'setDeadline', 'completedAt' => 'setCompletedAt'];
        foreach ($dateFields as $field => $setter) {
            if (!$request->request->has($field)) {
                continue;
            }
            $raw = trim((string) $request->request->get($field));
            if ('' === $raw) {
                $project->{$setter}(null);
            } else {
                try {
                    $project->{$setter}(new \DateTimeImmutable($raw));
                } catch (\Exception) {
                    $this->addFlash('error', 'Date invalide fournie pour le champ « '.$field.' ».');

                    return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
                }
            }
            $updated[] = $field;
        }

        if ([] === $updated) {
            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        $project->addToHistory('updated', $this->getAuthenticatedUser(), 'Paramètres mis à jour : '.implode(', ', $updated));
        $entityManager->flush();
        $this->addFlash('success', 'Paramètres du projet mis à jour.');

        return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
    }

    // =========================================================================
    // 📌 ÉTIQUETTES (TAGS) — édition inline, création à la volée
    // =========================================================================

    /**
     * Synchronise les étiquettes du projet depuis une liste de noms séparés par des
     * virgules. Les tags inexistants sont créés à la volée ; ceux retirés de la liste
     * sont détachés du projet (l'entité Tag n'est pas supprimée si réutilisée ailleurs).
     */
    #[Route('/{id}/tags', name: 'tags', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateTags(
        Project $project,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        if (!$this->isCsrfTokenValid('project_tags_'.$project->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        if ($this->isProjectLocked($project)) {
            $this->addFlash('error', 'Ce projet ne peut plus être modifié (statut : '.$project->getStatusLabel().').');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        // Noms nettoyés, dédoublonnés (insensible à la casse), longueur bornée.
        $names = [];
        foreach (explode(',', (string) $request->request->get('tags', '')) as $piece) {
            $name = trim($piece);
            if ('' === $name) {
                continue;
            }
            $name = mb_substr($name, 0, 100);
            $lower = mb_strtolower($name);
            if (!isset($names[$lower])) {
                $names[$lower] = $name;
            }
        }

        $tagRepository = $entityManager->getRepository(Tag::class);

        // Détacher les tags absents de la nouvelle liste.
        foreach ($project->getTags()->toArray() as $existing) {
            if (!isset($names[mb_strtolower($existing->getName())])) {
                $project->removeTag($existing);
            }
        }

        // Associer / créer les tags de la liste.
        foreach ($names as $lower => $name) {
            foreach ($project->getTags() as $current) {
                if (mb_strtolower($current->getName()) === $lower) {
                    continue 2; // déjà rattaché
                }
            }
            $tag = $tagRepository->findOneBy(['name' => $name]) ?? (new Tag())->setName($name);
            $entityManager->persist($tag);
            $project->addTag($tag);
        }

        $project->addToHistory('updated', $this->getAuthenticatedUser(), 'Étiquettes mises à jour');
        $entityManager->flush();
        $this->addFlash('success', 'Étiquettes mises à jour.');

        return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
    }

    // =========================================================================
    // 📌 AJOUT D'UNE DÉPENSE
    // =========================================================================

    #[Route('/{id}/expenses/new', name: 'add_expense', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function addExpense(
        Project $project,
        Request $request,
        \App\Service\ExpenseWorkflow $expenseWorkflow,
        \App\Service\ProjectNotifier $projectNotifier,
        MediaUploader $mediaUploader,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::ADD_EXPENSE, $project);

        // Double sécurité : empêche l'ajout de dépenses aux projets terminés ou suspendus
        if ($this->isProjectLocked($project)) {
            $this->addFlash('error', 'Impossible d\'ajouter une dépense à ce projet (statut : '.$project->getStatusLabel().').');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        // Prérequis « on ne dépense pas sans argent » : un budget doit être défini.
        if (!$project->hasBudget()) {
            $this->addFlash('error', 'Définissez d\'abord un budget (> 0) pour ce projet avant d\'ajouter une dépense.');

            return $this->redirectToRoute('admin_project_update', ['id' => $project->getId()]);
        }

        $expense = new ProjectExpense();
        $form = $this->createForm(ProjectExpenseType::class, $expense);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Justificatif optionnel (reçu/facture) — champ non mappé.
            $receipt = $form->get('receipt')->getData();
            if ($receipt instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $uploaded = $mediaUploader->upload($receipt, 'receipts');
                $expense->setReceiptPath($uploaded['path']);
            }

            try {
                // Soumise en « en attente » : elle n'impacte le budget qu'après approbation.
                $expenseWorkflow->submit($project, $expense, $this->getAuthenticatedUser());
                $projectNotifier->expenseSubmitted($expense);
                $this->addFlash('success', 'Dépense soumise — en attente d\'approbation.');

                return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/project/expense/new.html.twig', [
            'project' => $project,
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 MODIFICATION D'UNE DÉPENSE
    // =========================================================================

    #[Route('/{id}/expenses/{expenseId}/update', name: 'update_expense', methods: ['GET', 'POST'], requirements: ['id' => '\d+', 'expenseId' => '\d+'])]
    public function updateExpense(
        Project $project,
        #[MapEntity(id: 'expenseId')] ProjectExpense $expense,
        Request $request,
        \App\Service\ExpenseWorkflow $expenseWorkflow,
        MediaUploader $mediaUploader,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::ADD_EXPENSE, $project);

        // Vérifier que la dépense appartient bien au projet
        if ($expense->getProject() !== $project) {
            $this->addFlash('error', 'Dépense introuvable ou non associée à ce projet.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        // Double sécurité : empêche la modification de dépenses sur projets terminés ou suspendus
        if ($this->isProjectLocked($project)) {
            $this->addFlash('error', 'Impossible de modifier une dépense de ce projet (statut : '.$project->getStatusLabel().').');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        $form = $this->createForm(ProjectExpenseType::class, $expense);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Justificatif optionnel : remplace le précédent si un nouveau fichier est fourni.
            $receipt = $form->get('receipt')->getData();
            if ($receipt instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $uploaded = $mediaUploader->upload($receipt, 'receipts');
                $expense->setReceiptPath($uploaded['path']);
            }

            try {
                $expenseWorkflow->update($expense, $this->getAuthenticatedUser());
                $this->addFlash('success', 'Dépense modifiée avec succès.');

                return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/project/expense/edit.html.twig', [
            'project' => $project,
            'expense' => $expense,
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 SUPPRESSION D'UNE DÉPENSE
    // =========================================================================

    #[Route('/{id}/expenses/{expenseId}/delete', name: 'delete_expense', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteExpense(
        Project $project,
        #[MapEntity(id: 'expenseId')] ProjectExpense $expense,
        EntityManagerInterface $entityManager,
        Request $request,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::ADD_EXPENSE, $project);

        // Vérifier que la dépense appartient bien au projet
        if ($expense->getProject() !== $project) {
            $this->addFlash('error', 'Dépense introuvable ou non associée à ce projet.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        // Double sécurité : empêche la suppression de dépenses aux projets terminés ou suspendus
        if ($this->isProjectLocked($project)) {
            $this->addFlash('error', 'Impossible de supprimer une dépense de ce projet (statut : '.$project->getStatusLabel().').');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        if ($this->isCsrfTokenValid('delete_expense_'.$expense->getId(), $request->request->get('_token'))) {
            // Suppression de la dépense et journalisation
            $project->removeProjectExpense($expense);
            $entityManager->flush();
            $this->addFlash('success', 'Dépense supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
    }

    // =========================================================================
    // 📌 TÂCHES / JALONS (suivi étape par étape)
    // =========================================================================

    #[Route('/{id}/tasks/add', name: 'task_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addTask(Project $project, Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, \App\Service\ProjectNotifier $projectNotifier): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_TASK, $project);

        if (!$this->isCsrfTokenValid('task_add_'.$project->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        $title = trim((string) $request->request->get('title'));
        if ('' === $title) {
            $this->addFlash('error', 'Le titre de la tâche est obligatoire.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        $task = new ProjectTask();
        $task->setTitle($title)->setPosition($project->getTasksCount());

        // Responsable optionnel — doit faire partie de l'équipe du projet.
        $assigneeId = $request->request->getInt('assignee', 0);
        if ($assigneeId > 0) {
            $assignee = $userRepository->find($assigneeId);
            if ($assignee && $project->isTeamMember($assignee)) {
                $task->setAssignee($assignee);
            } else {
                $this->addFlash('error', 'Le responsable doit faire partie de l\'équipe du projet.');

                return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
            }
        }

        $dueDate = trim((string) $request->request->get('dueDate'));
        if ('' !== $dueDate) {
            $task->setDueDate(new \DateTimeImmutable($dueDate));
        }

        $project->addTask($task);
        $project->addToHistory('task_added', $this->getAuthenticatedUser(), 'Tâche ajoutée : '.$title);
        $entityManager->flush();

        $projectNotifier->taskAssigned($task);
        $this->addFlash('success', 'Tâche ajoutée.');

        return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
    }

    #[Route('/{id}/tasks/{taskId}/status/{status}', name: 'task_status', methods: ['POST'], requirements: ['id' => '\d+', 'taskId' => '\d+'])]
    public function taskStatus(
        Project $project,
        #[MapEntity(id: 'taskId')] ProjectTask $task,
        string $status,
        EntityManagerInterface $entityManager,
        Request $request,
        \App\Service\ProjectNotifier $projectNotifier,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_TASK, $project);

        if ($task->getProject() !== $project) {
            $this->addFlash('error', 'Tâche introuvable pour ce projet.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        if (!$this->isCsrfTokenValid('task_status_'.$task->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        try {
            $newStatus = TaskStatusEnum::from($status);
        } catch (\ValueError) {
            $this->addFlash('error', 'Statut de tâche invalide.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        $task->setStatus($newStatus);
        $project->recalculateProgress();
        $project->addToHistory(
            $newStatus->isDone() ? 'task_completed' : 'task_updated',
            $this->getAuthenticatedUser(),
            sprintf('Tâche « %s » → %s', $task->getTitle(), $newStatus->getLabel()),
        );
        $entityManager->flush();
        $projectNotifier->taskStatusChanged($task);

        return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
    }

    #[Route('/{id}/tasks/{taskId}/delete', name: 'task_delete', methods: ['POST'], requirements: ['id' => '\d+', 'taskId' => '\d+'])]
    public function deleteTask(
        Project $project,
        #[MapEntity(id: 'taskId')] ProjectTask $task,
        EntityManagerInterface $entityManager,
        Request $request,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_TASK, $project);

        if ($task->getProject() === $project && $this->isCsrfTokenValid('task_delete_'.$task->getId(), $request->request->get('_token'))) {
            $title = $task->getTitle();
            $project->removeTask($task);
            $project->addToHistory('task_removed', $this->getAuthenticatedUser(), 'Tâche supprimée : '.$title);
            $entityManager->flush();
            $this->addFlash('success', 'Tâche supprimée.');
        } else {
            $this->addFlash('error', 'Action impossible (jeton invalide ou tâche invalide).');
        }

        return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
    }

    // =========================================================================
    // 📌 COMMENTAIRES (discussion projet)
    // =========================================================================

    #[Route('/{id}/comments/add', name: 'add_comment', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addComment(Project $project, Request $request, EntityManagerInterface $entityManager, \App\Service\ProjectNotifier $projectNotifier): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        if (!$this->isCsrfTokenValid('comment_add_'.$project->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        $content = trim((string) $request->request->get('content'));
        if ('' === $content) {
            $this->addFlash('error', 'Le commentaire ne peut pas être vide.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        $comment = new Comment();
        $comment->setContent($content)->setAuthor($this->getAuthenticatedUser());
        $project->addComment($comment);
        $entityManager->persist($comment);
        $entityManager->flush();
        $projectNotifier->commentPosted($comment);

        $this->addFlash('success', 'Commentaire ajouté.');

        return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
    }

    #[Route('/{id}/comments/{commentId}/delete', name: 'delete_comment', methods: ['POST'], requirements: ['id' => '\d+', 'commentId' => '\d+'])]
    public function deleteComment(
        Project $project,
        #[MapEntity(id: 'commentId')] Comment $comment,
        EntityManagerInterface $entityManager,
        Request $request,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        // Seul l'auteur ou un administrateur peut supprimer un commentaire.
        $isAuthor = $comment->getAuthor() === $this->getAuthenticatedUser();
        if ($comment->getProject() !== $project || (!$isAuthor && !$this->isGranted('ROLE_ADMIN'))) {
            $this->addFlash('error', 'Suppression non autorisée.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        if ($this->isCsrfTokenValid('comment_delete_'.$comment->getId(), $request->request->get('_token'))) {
            $project->removeComment($comment);
            $entityManager->flush();
            $this->addFlash('success', 'Commentaire supprimé.');
        }

        return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
    }

    // =========================================================================
    // 📌 SUIVI DU TEMPS
    // =========================================================================

    #[Route('/{id}/time/add', name: 'add_time', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addTime(Project $project, Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_TASK, $project);

        if (!$this->isCsrfTokenValid('time_add_'.$project->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        $hours = (int) $request->request->get('hours', 0);
        $minutes = (int) $request->request->get('minutes', 0);
        $total = $hours * 60 + $minutes;
        if ($total <= 0) {
            $this->addFlash('error', 'La durée doit être strictement positive.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        $entry = new TimeEntry();
        $entry->setMinutes($total)->setDescription(trim((string) $request->request->get('description')) ?: null);

        // Auteur du temps : par défaut l'admin courant ; sinon un membre de l'équipe choisi.
        $userId = $request->request->getInt('user', 0);
        $author = $userId > 0 ? $userRepository->find($userId) : $this->getAuthenticatedUser();
        if (!$author instanceof User || (!$project->isTeamMember($author))) {
            $author = $this->getAuthenticatedUser();
        }
        $entry->setUser($author);

        $spentOn = trim((string) $request->request->get('spentOn'));
        if ('' !== $spentOn) {
            try {
                $entry->setSpentOn(new \DateTimeImmutable($spentOn));
            } catch (\Exception) {
                // conserve la date du jour par défaut
            }
        }

        // Tâche optionnelle
        $taskId = $request->request->getInt('task', 0);
        if ($taskId > 0) {
            foreach ($project->getTasks() as $t) {
                if ($t->getId() === $taskId) {
                    $entry->setTask($t);
                    break;
                }
            }
        }

        $project->addTimeEntry($entry);
        $entityManager->persist($entry);
        $entityManager->flush();

        $this->addFlash('success', 'Temps enregistré.');

        return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
    }

    #[Route('/{id}/time/{entryId}/delete', name: 'delete_time', methods: ['POST'], requirements: ['id' => '\d+', 'entryId' => '\d+'])]
    public function deleteTime(
        Project $project,
        #[MapEntity(id: 'entryId')] TimeEntry $entry,
        EntityManagerInterface $entityManager,
        Request $request,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_TASK, $project);

        if ($entry->getProject() === $project && $this->isCsrfTokenValid('time_delete_'.$entry->getId(), $request->request->get('_token'))) {
            $project->removeTimeEntry($entry);
            $entityManager->flush();
            $this->addFlash('success', 'Saisie de temps supprimée.');
        }

        return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
    }

    // =========================================================================
    // 📌 ASSIGNATION D'UN CLIENT
    // =========================================================================

    #[Route('/{id}/client/assign', name: 'assign_client', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function assignClient(
        Project $project,
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        if (!$this->isCsrfTokenValid('assign_client_'.$project->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        $email = trim((string) $request->request->get('email'));
        $user = '' !== $email ? $userRepository->findOneBy(['email' => $email]) : null;

        if (!$user) {
            $this->addFlash('error', 'Aucun utilisateur trouvé avec cet email.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        // Règle métier : seul un compte "client pur" (ni admin, ni collaborateur) peut être assigné.
        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_EDITOR', $roles, true)) {
            $this->addFlash('error', 'Seul un compte client (ni administrateur, ni collaborateur) peut être assigné comme client.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        if ($project->getCollaborators()->contains($user)) {
            $this->addFlash('error', 'Cet utilisateur est déjà collaborateur de ce projet ; il ne peut pas aussi en être le client.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        $oldClient = $project->getClient();
        $project->setClient($user);
        $project->addToHistory(
            'client_assigned',
            $this->getAuthenticatedUser(),
            sprintf(
                'Client %s : "%s" → "%s".',
                $oldClient ? 'remplacé' : 'assigné',
                $oldClient?->getFullName() ?? 'aucun',
                $user->getFullName()
            )
        );

        $entityManager->flush();
        $this->addFlash('success', 'Client assigné avec succès.');

        return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
    }

    // =========================================================================
    // 📌 AJOUT D'UN COLLABORATEUR
    // =========================================================================

    #[Route('/{id}/collaborators/add', name: 'add_collaborator', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function addCollaborator(
        Project $project,
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        \App\Service\ProjectNotifier $projectNotifier,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::ADD_COLLABORATOR, $project);

        $form = $this->createFormBuilder()
            ->add('fullName', EntityType::class, [
                'class' => User::class,
                'label' => 'Sélectionner le collaborateur',
                'choice_label' => function (User $user) {
                    return $user->getFullName()
                        ? sprintf('%s (%s)', $user->getFullName(), $user->getEmail())
                        : $user->getEmail();
                },
                'placeholder' => 'Rechercher un membre du staff...',
                'attr' => ['class' => 'form-control'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('fullName')->getData();
            $user = $userRepository->findOneBy(['fullName' => $email]);

            if (!$user) {
                $this->addFlash('error', 'Aucun utilisateur trouvé avec cet email.');

                return $this->redirectToRoute('admin_project_add_collaborator', ['id' => $project->getId()]);
            }

            if ($project->getCollaborators()->contains($user)) {
                $this->addFlash('error', 'Cet utilisateur est déjà un collaborateur de ce projet.');

                return $this->redirectToRoute('admin_project_add_collaborator', ['id' => $project->getId()]);
            }

            if ($user === $project->getClient()) {
                $this->addFlash('error', 'Le client de ce projet ne peut pas être ajouté comme collaborateur.');

                return $this->redirectToRoute('admin_project_add_collaborator', ['id' => $project->getId()]);
            }

            $project->addCollaborator($user);
            $project->logCollaboratorAdded($this->getAuthenticatedUser(), $user);

            $entityManager->flush();
            $projectNotifier->collaboratorAdded($project, $user);
            $this->addFlash('success', 'Collaborateur ajouté avec succès.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        return $this->render('admin/project/collaborator/add.html.twig', [
            'project' => $project,
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 SUPPRESSION D'UN COLLABORATEUR
    // =========================================================================

    #[Route('/{id}/collaborators/{collaboratorId}/remove', name: 'remove_collaborator', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function removeCollaborator(
        Project $project,
        #[MapEntity(id: 'collaboratorId')] User $collaborator,
        EntityManagerInterface $entityManager,
        Request $request,
        \App\Service\ProjectNotifier $projectNotifier,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::ADD_COLLABORATOR, $project);

        if ($this->isCsrfTokenValid('remove_collaborator_'.$collaborator->getId(), $request->request->get('_token'))) {
            $project->removeCollaborator($collaborator);
            $project->logCollaboratorRemoved($this->getAuthenticatedUser(), $collaborator);

            $entityManager->flush();
            $projectNotifier->collaboratorRemoved($project, $collaborator);
            $this->addFlash('success', 'Collaborateur retiré avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
    }

    // =========================================================================
    // 🔧 MÉTHODES UTILITAIRES PRIVÉES
    // =========================================================================

    /**
     * Récupère l'utilisateur authentifié en tant qu'App\Entity\User.
     * Ces routes sont toutes protégées en amont (ROLE_ADMIN via le firewall
     * et/ou ProjectVoter), donc un utilisateur non-User ne peut pas les atteindre.
     */
    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Un utilisateur App\Entity\User authentifié est requis ici.');
        }

        return $user;
    }

    /**
     * Vérifie si un projet est verrouillé (terminé ou suspendu).
     */
    private function isProjectLocked(Project $project): bool
    {
        return in_array($project->getStatus(), [
            ProjectStatusEnum::COMPLETED,
            ProjectStatusEnum::SUSPENDED,
        ], true);
    }

    /**
     * Gère l'upload des médias pour un projet.
     */
    private function handleMediaUpload(
        Project $project,
        FormInterface $form,
        EntityManagerInterface $entityManager,
        MediaUploader $mediaUploader,
    ): void {
        $mediaFiles = $form->get('media')->getData();

        if ($mediaFiles && count($mediaFiles) > 0) {
            $results = $mediaUploader->uploadMultiple($mediaFiles, 'projects');

            foreach ($results as $result) {
                $media = new Media();
                $media
                    ->setFilePath($result['path'])
                    ->setAltText($project->getTitle())
                    ->setMimeType($result['mimeType'])
                    ->setSize($result['size'])
                    ->setType($result['type'])
                    ->setUploadedAt($result['uploadedAt']);

                $entityManager->persist($media);
                $project->addMedia($media);
            }
        }
    }

    /**
     * Journalise les modifications apportées à un projet.
     */
    private function logProjectChanges(
        Project $project,
        ProjectStatusEnum $oldStatus,
        string $oldBudget,
        string $oldSpent,
    ): void {
        $changes = [];

        // Vérifier le changement de budget
        if ($oldBudget !== $project->getBudget()) {
            $changes['Budget'] = sprintf(
                '%s€ → %s€',
                number_format((float) $oldBudget, 2, ',', ' '),
                number_format((float) $project->getBudget(), 2, ',', ' ')
            );
        }

        // Vérifier le changement de dépenses
        if ($oldSpent !== $project->getSpent()) {
            $changes['Dépenses'] = sprintf(
                '%s€ → %s€',
                number_format((float) $oldSpent, 2, ',', ' '),
                number_format((float) $project->getSpent(), 2, ',', ' ')
            );
        }

        // Vérifier le changement de statut
        if ($oldStatus !== $project->getStatus()) {
            $changes['Statut'] = sprintf(
                '%s → %s',
                $oldStatus->getLabel(),
                $project->getStatus()->getLabel()
            );
        }

        // Journaliser les modifications
        if (!empty($changes)) {
            $project->logUpdate($this->getAuthenticatedUser(), $changes);
        }
    }
}
