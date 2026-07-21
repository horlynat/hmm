<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\Project;
use App\Entity\ProjectExpense;
use App\Entity\User;
use App\Enum\BillingTypeEnum;
use App\Enum\BudgetStatusEnum;
use App\Enum\ProjectPriorityEnum;
use App\Enum\ProjectStatusEnum;
use App\Form\ProjectExpenseType;
use App\Form\ProjectType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
use Symfony\Component\HttpKernel\Attribute\MapEntity;
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

        // 📌 Historique récent (pour le dashboard)
        $recentHistory = $projectRepository->createQueryBuilder('p')
            ->join('p.histories', 'h')
            ->orderBy('h.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // 📌 Dépenses récentes (pour le dashboard)
        $recentExpenses = $projectRepository->createQueryBuilder('p')
            ->join('p.expenses', 'e')
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

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
            'recentExpenses' => $recentExpenses,
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
        $project->setOwner($this->getUser());

        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Générer le slug
            $slug = $slugger->slug($project->getTitle())->lower();
            $project->setSlug($slug);

            // Upload des médias
            $this->handleMediaUpload($project, $form, $entityManager, $mediaUploader);

            // Journaliser la création
            $project->logCreation($this->getUser());

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
            $mediaUploader->deleteFile($media->getFilePath(), 'projects');

            $project->removeMedia($media);
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
            $project->addToHistory('project_deleted', $this->getUser(), 'Projet supprimé');

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
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

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

            // Journaliser le changement de statut
            $project->logStatusChange(
                $this->getUser(),
                $oldStatus->getLabel(),
                $newStatus->getLabel()
            );

            $project->setStatus($newStatus);
            $entityManager->flush();

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
    // 📌 AJOUT D'UNE DÉPENSE
    // =========================================================================

    #[Route('/{id}/expenses/new', name: 'add_expense', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function addExpense(
        Project $project,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        // Double sécurité : empêche l'ajout de dépenses aux projets terminés ou suspendus
        if ($this->isProjectLocked($project)) {
            $this->addFlash('error', 'Impossible d\'ajouter une dépense à ce projet (statut : '.$project->getStatusLabel().').');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        $expense = new ProjectExpense();
        $form = $this->createForm(ProjectExpenseType::class, $expense);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Ajout de la dépense et journalisation
            $project->addProjectExpense(
                $expense->getAmount(),
                $expense->getDescription() ?? '',
                $this->getUser()
            );

            $entityManager->flush();
            $this->addFlash('success', 'Dépense ajoutée avec succès.');

            return $this->redirectToRoute('admin_project_read', ['id' => $project->getId()]);
        }

        return $this->render('admin/project/expense/new.html.twig', [
            'project' => $project,
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
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

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
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_COLLABORATOR', $roles, true)) {
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
            $this->getUser(),
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
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

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
            $project->logCollaboratorAdded($this->getUser(), $user);

            $entityManager->flush();
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
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        if ($this->isCsrfTokenValid('remove_collaborator_'.$collaborator->getId(), $request->request->get('_token'))) {
            $project->removeCollaborator($collaborator);
            $project->logCollaboratorRemoved($this->getUser(), $collaborator);

            $entityManager->flush();
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
                    ->setAltText($project->getTitle() ?? 'Project Media')
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
            $project->logUpdate($this->getUser(), $changes);
        }
    }
}
