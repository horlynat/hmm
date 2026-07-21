<?php

namespace App\Controller\Admin;

use App\Entity\Project;
use App\Enum\ProjectStatusEnum;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Service\ProjectStatisticsService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur du Tableau de bord Central de l'administration.
 *
 * 🔒 Sécurité :
 * - Accès strictement restreint au rôle ROLE_ADMIN.
 * - Protection contre les injections DQL sur les tris dynamiques.
 */
#[Route('/admin/dashboard', name: 'admin_dashboard_')]
class AdminDashboardController extends AbstractController
{
    // =========================================================================
    // 📌 VUE D'ENSEMBLE ET STATISTIQUES (INDEX)
    // =========================================================================

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        ProjectRepository $projectRepository,
        UserRepository $userRepository,
        ProjectStatisticsService $statisticsService,
        EntityManagerInterface $entityManager 
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Récupération des statistiques budgétaires globales
        $stats = Project::getBudgetStatistics($entityManager);

        // Nombre de projets groupés par statut
        $projectsByStatus = [];
        foreach (ProjectStatusEnum::cases() as $status) {
            $projectsByStatus[$status->value] = [
                'label' => $status->getLabel(),
                'count' => $projectRepository->count(['status' => $status]),
                'badgeClass' => $status->getBadgeClass(),
            ];
        }

        // Listes de performances et alertes (Top 5)
        $topBudgetProjects = $projectRepository->findBy([], ['budget' => 'DESC'], 5);
        $topSpentProjects = $projectRepository->findBy([], ['spent' => 'DESC'], 5);

        // Projets en dépassement de budget
        $overBudgetProjects = $projectRepository->createQueryBuilder('p')
            ->where('p.spent > p.budget')
            ->getQuery()
            ->getResult();

        // Projets avec un budget restant faible (< 10%)
        $lowBudgetProjects = $projectRepository->createQueryBuilder('p')
            ->where('p.budget > 0 AND (p.budget - p.spent) / p.budget < 0.1')
            ->getQuery()
            ->getResult();

        // Utilisateurs les plus actifs (propriétaires de projets)
        $activeUsers = $userRepository->createQueryBuilder('u')
            ->join('u.ownedProjects', 'p')
            ->groupBy('u.id')
            ->orderBy('COUNT(p.id)', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return $this->render('admin/dashboard/index.html.twig', [
            'stats' => $stats,
            'projectsByStatus' => $projectsByStatus,
            'topBudgetProjects' => $topBudgetProjects,
            'topSpentProjects' => $topSpentProjects,
            'overBudgetProjects' => $overBudgetProjects,
            'lowBudgetProjects' => $lowBudgetProjects,
            'activeUsers' => $activeUsers,
            'chartData' => $statisticsService->getChartData(),
        ]);
    }

    // =========================================================================
    // 📌 EXPLORATEUR DE PROJETS FILTRÉ
    // =========================================================================

    #[Route('/projects', name: 'projects', methods: ['GET'])]
    public function projects(
        ProjectRepository $projectRepository,
        UserRepository $userRepository,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Récupération des filtres et paramètres
        $status = $request->query->get('status');
        $budgetStatus = $request->query->get('budget_status');
        $ownerId = $request->query->get('owner');
        $collaboratorId = $request->query->get('collaborator');
        $search = $request->query->get('search');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;

        // 🛡️ Sécurisation stricte du tri (Anti-Injection DQL)
        $sort = $request->query->get('sort', 'createdAt');
        $direction = $request->query->get('direction', 'desc');

        $allowedProjectSorts = ['createdAt', 'title', 'budget', 'spent', 'status'];
        if (!in_array($sort, $allowedProjectSorts, true)) {
            $sort = 'createdAt';
        }
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        // Construction sécurisée de la requête avec Jointure
        $queryBuilder = $projectRepository->createQueryBuilder('p')
            ->leftJoin('p.owner', 'o'); // ✅ Requis pour chercher par email sans crash

        // Application des filtres conditionnels
        if ($status) {
            $queryBuilder->andWhere('p.status = :status')
                ->setParameter('status', $status);
        }

        if ($budgetStatus) {
            switch ($budgetStatus) {
                case 'over':
                    $queryBuilder->andWhere('p.spent > p.budget');
                    break;
                case 'low':
                    $queryBuilder->andWhere('p.budget > 0 AND (p.budget - p.spent) / p.budget < 0.1');
                    break;
                case 'ok':
                    $queryBuilder->andWhere('p.spent <= p.budget AND (p.budget = 0 OR (p.budget - p.spent) / p.budget >= 0.1)');
                    break;
            }
        }

        if ($ownerId) {
            $queryBuilder->andWhere('p.owner = :owner')
                ->setParameter('owner', $ownerId);
        }

        if ($collaboratorId) {
            $queryBuilder->join('p.collaborators', 'c')
                ->andWhere('c.id = :collaborator')
                ->setParameter('collaborator', $collaboratorId);
        }

        if ($search) {
            // ✅ Correction de la chaîne : o.email au lieu de p.owner.email
            $queryBuilder->andWhere('p.title LIKE :search OR p.description LIKE :search OR o.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Application du tri validé et sécurisé
        $queryBuilder->orderBy('p.' . $sort, $direction);

        // Traitement de la pagination
        $paginator = new Paginator($queryBuilder);
        $totalPages = (int) ceil($paginator->count() / $limit);
        
        $projects = $paginator
            ->getQuery()
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getResult();

        return $this->render('admin/dashboard/projects.html.twig', [
            'projects' => $projects,
            'users' => $userRepository->findAll(),
            'statuses' => ProjectStatusEnum::cases(),
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => $request->query->all(),
        ]);
    }

    // =========================================================================
    // 📌 EXPLORATEUR D'UTILISATEURS
    // =========================================================================

    #[Route('/users', name: 'users', methods: ['GET'])]
    public function users(UserRepository $userRepository, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $search = $request->query->get('search');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;

        // 🛡️ Sécurisation stricte du tri (Anti-Injection DQL)
        $sort = $request->query->get('sort', 'createdAt');
        $direction = $request->query->get('direction', 'desc');

        $allowedUserSorts = ['createdAt', 'email', 'fullName'];
        if (!in_array($sort, $allowedUserSorts, true)) {
            $sort = 'createdAt';
        }
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        $queryBuilder = $userRepository->createQueryBuilder('u');

        if ($search) {
            $queryBuilder->andWhere('u.email LIKE :search OR u.fullName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Application du tri validé
        $queryBuilder->orderBy('u.' . $sort, $direction);

        // Traitement de la pagination
        $paginator = new Paginator($queryBuilder);
        $totalPages = (int) ceil($paginator->count() / $limit);
        
        $users = $paginator
            ->getQuery()
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getResult();

        return $this->render('admin/dashboard/users.html.twig', [
            'users' => $users,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => $request->query->all(),
        ]);
    }
}
