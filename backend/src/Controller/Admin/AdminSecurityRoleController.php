<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Voter\SecurityVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Hiérarchie des rôles et matrice de permissions — page interactive, pas un
 * rapport figé : chaque rôle se déplie sur la liste réelle des comptes qui le
 * portent (show()), avec un lien direct vers la bonne page d'édition. La
 * boucle se referme là : voir le rôle → voir qui l'a → agir sur ce compte
 * (édition, y compris changer son rôle) → revenir voir le résultat mis à jour.
 *
 * La mutation elle-même (formulaire de rôles) n'est PAS dupliquée ici : elle
 * reste dans AdminUsersController/AdminCollaboratorController/AdminAdminsController
 * (source unique — auto-rétrogradation bloquée, alerte d'élévation de
 * privilèges, audit — cf. leurs docblocks). Cette page ne fait qu'orienter
 * vers le bon contrôleur selon le rôle actuel du compte.
 *
 * 🔒 Sécurité : réservé à SecurityVoter::VIEW_ROLES (ROLE_ADMIN et plus).
 */
#[Route('/admin/security/roles', name: 'admin_security_role_')]
class AdminSecurityRoleController extends AbstractController
{
    /** Chaîne de la hiérarchie, du rôle le plus faible au plus fort (cf. security.yaml). */
    private const ROLES = ['USER', 'EDITOR', 'MODERATOR', 'MANAGER', 'ADMIN', 'SUPER_ADMIN'];

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted(SecurityVoter::VIEW_ROLES);

        return $this->render('admin/security/roles.html.twig', [
            'roles' => self::ROLES,
            'permissionGroups' => $this->getPermissionMatrix(),
            'roleCounts' => $this->countUsersByRole($userRepository->findAll()),
        ]);
    }

    /**
     * Comptes portant un rôle donné comme rôle principal — clic depuis
     * index() sur un badge de la hiérarchie. Calculé en mémoire sur
     * User::getPrimaryRole() (même logique que countUsersByRole(), donc
     * les chiffres de la page précédente et cette liste ne peuvent pas
     * diverger) plutôt que via les requêtes UserRepository::findClients()/
     * findCollaborators()/findAdmins(), dont les filtres LIKE ciblent des
     * rôles précis et laisseraient passer, par exemple, un compte
     * ROLE_MODERATOR seul (sans ROLE_EDITOR) sans le faire apparaître ici.
     */
    #[Route('/{role}', name: 'show', methods: ['GET'], requirements: ['role' => 'USER|EDITOR|MODERATOR|MANAGER|ADMIN|SUPER_ADMIN'])]
    public function show(string $role, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted(SecurityVoter::VIEW_ROLES);

        $allUsers = $userRepository->findAll();
        $members = array_values(array_filter($allUsers, static fn (User $user): bool => $user->getPrimaryRole() === 'ROLE_' . $role));
        usort($members, static fn (User $a, User $b): int => strcasecmp($a->getFullName() ?? $a->getEmail(), $b->getFullName() ?? $b->getEmail()));

        return $this->render('admin/security/role_members.html.twig', [
            'role' => $role,
            'roles' => self::ROLES,
            'members' => array_map(fn (User $user) => [
                'user' => $user,
                'editRoute' => $this->accountRouteFor($user, 'update'),
                'readRoute' => $this->accountRouteFor($user, 'read'),
            ], $members),
            'roleCounts' => $this->countUsersByRole($allUsers),
        ]);
    }

    /**
     * Le back-office historique répartit l'édition d'un compte sur trois
     * contrôleurs selon son rôle (cf. leurs docblocks respectifs) — jamais un
     * seul "AdminUserController" générique. On route ici vers le bon, sans
     * dupliquer leur logique de formulaire.
     */
    private function accountRouteFor(User $user, string $action): string
    {
        $primaryRole = $user->getPrimaryRole();
        $controller = match (true) {
            \in_array($primaryRole, ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], true) => 'admin_admins_',
            \in_array($primaryRole, ['ROLE_EDITOR', 'ROLE_MODERATOR', 'ROLE_MANAGER'], true) => 'admin_collaborator_',
            default => 'admin_users_',
        };

        return $controller . $action;
    }

    /**
     * @return array<string, array<string, int>> Groupe => [permission => index minimum dans self::ROLES]
     */
    private function getPermissionMatrix(): array
    {
        return [
            'Projets' => [
                'PROJECT_VIEW' => 0,
                'PROJECT_EDIT' => 1,
                'PROJECT_DELETE' => 3,
                'PROJECT_MANAGE_BUDGET' => 3,
                'PROJECT_CHANGE_STATUS' => 1,
            ],
            'Articles' => [
                'ARTICLE_VIEW' => 0,
                'ARTICLE_CREATE' => 1,
                'ARTICLE_EDIT' => 1,
                'ARTICLE_DELETE' => 2,
                'ARTICLE_PUBLISH' => 2,
            ],
            'Utilisateurs' => [
                'USER_VIEW' => 2,
                'USER_EDIT' => 3,
                'USER_DELETE' => 4,
                'USER_BAN' => 2,
                'USER_IMPERSONATE' => 5,
                'USER_CHANGE_ROLE' => 4,
            ],
            'Contacts' => [
                'CONTACT_VIEW' => 2,
                'CONTACT_REPLY' => 2,
                'CONTACT_DELETE' => 3,
            ],
            'Devis' => [
                'QUOTE_VIEW' => 3,
                'QUOTE_APPROVE' => 3,
                'QUOTE_CONVERT' => 4,
            ],
            'Témoignages' => [
                'TESTIMONIAL_APPROVE' => 2,
                'TESTIMONIAL_FEATURE' => 3,
            ],
            'Sécurité' => [
                'SECURITY_VIEW_LOGS' => 4,
                'SECURITY_FORCE_LOGOUT' => 4,
                'SECURITY_MANAGE_SESSIONS' => 4,
                'SECURITY_MANAGE_2FA' => 4,
                'SECURITY_VIEW_POLICIES' => 4,
                'SECURITY_MANAGE_IP_BLOCKS' => 4,
                'SECURITY_MANAGE_LOGS' => 4,
                'SECURITY_VIEW_AUDIT' => 4,
                'DASHBOARD_EXPORT' => 3,
            ],
        ];
    }

    /**
     * @param User[] $allUsers
     *
     * @return array<string, int> Rôle => nombre de comptes (chaque compte compté une fois, sous son rôle principal)
     */
    private function countUsersByRole(array $allUsers): array
    {
        $counts = array_fill_keys(self::ROLES, 0);

        foreach ($allUsers as $user) {
            $primaryRole = str_replace('ROLE_', '', $user->getPrimaryRole());
            if (isset($counts[$primaryRole])) {
                ++$counts[$primaryRole];
            }
        }

        return $counts;
    }
}
