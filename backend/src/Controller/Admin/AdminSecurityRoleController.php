<?php

namespace App\Controller\Admin;

use App\Entity\PermissionDefinition;
use App\Entity\User;
use App\Repository\PermissionDefinitionRepository;
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
    public function index(UserRepository $userRepository, PermissionDefinitionRepository $permissionDefinitionRepository): Response
    {
        $this->denyAccessUnlessGranted(SecurityVoter::VIEW_ROLES);

        $allUsers = $userRepository->findAll();

        return $this->render('admin/security/roles.html.twig', [
            'roles' => self::ROLES,
            'permissionGroups' => $this->getDynamicPermissionMatrix($permissionDefinitionRepository),
            'fixedPermissionGroups' => $this->getFixedPermissionMatrix(),
            'contextualRules' => $this->getContextualRules(),
            'roleCounts' => $this->countUsersByRole($allUsers),
            'privilegedUsers' => $this->getPrivilegedUsers($allUsers),
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
     * Catalogue dynamique (Articles, Contacts, Formations, Tableau de bord,
     * Finance, Devis, Compétences, Support, Témoignages) — lu en direct
     * depuis PermissionDefinition/Role (cf. PermissionRegistry), jamais
     * recopié à la main : source unique avec la page d'édition
     * (/admin/security/permissions/), qui consulte les mêmes lignes.
     *
     * @return array<string, array<string, int>> Groupe => [code => rang minimum (Role::rank, 0-5)]
     */
    private function getDynamicPermissionMatrix(PermissionDefinitionRepository $repository): array
    {
        $matrix = [];
        foreach ($repository->findAllOrdered() as $definition) {
            /** @var PermissionDefinition $definition */
            $matrix[$definition->getCategory()][$definition->getCode()] = $definition->getCurrentRole()->getRank();
        }

        return $matrix;
    }

    /**
     * Sécurité et Paramètres : seuils codés en dur dans les Voters
     * eux-mêmes (SecurityVoter/SettingsVoter), jamais consultables via
     * PermissionRegistry — cf. PermissionRegistry::NON_OVERRIDABLE_PREFIXES.
     * Recopiés ici pour affichage uniquement ; en cas de désaccord, le code
     * du Voter fait foi. À tenir synchronisé si un Voter change.
     *
     * @return array<string, array<string, int>> Groupe => [code => rang minimum]
     */
    private function getFixedPermissionMatrix(): array
    {
        return [
            'Sécurité' => [
                'SECURITY_VIEW_LOGS' => 4,
                'SECURITY_MANAGE_2FA' => 4,
                'SECURITY_FORCE_LOGOUT' => 4,
                'SECURITY_MANAGE_SESSIONS' => 4,
                'SECURITY_VIEW_ROLES' => 4,
                'SECURITY_VIEW_POLICIES' => 4,
                'SECURITY_VIEW_AUDIT' => 4,
                'SECURITY_MANAGE_IP_BLOCKS' => 4,
                'SECURITY_MANAGE_LOGS' => 4,
                'SECURITY_MANAGE_PERMISSIONS' => 5,
            ],
            'Paramètres' => [
                'SETTINGS_VIEW_CONFIG' => 4,
                'SETTINGS_MANAGE_CONFIG' => 4,
                'SETTINGS_VIEW_NOTIFICATIONS' => 4,
                'SETTINGS_MANAGE_NOTIFICATIONS' => 4,
                'SETTINGS_VIEW_INTEGRATIONS' => 4,
                'SETTINGS_MANAGE_INTEGRATIONS' => 4,
                'SETTINGS_VIEW_BACKUPS' => 4,
                'SETTINGS_CREATE_BACKUP' => 4,
                'SETTINGS_DOWNLOAD_BACKUP' => 4,
                'SETTINGS_DELETE_BACKUP' => 5,
                'SETTINGS_RESTORE_BACKUP' => 5,
            ],
        ];
    }

    /**
     * Project/User (ABAC) : pas un simple seuil de rôle, donc pas de grille
     * de cases (trompeur — cf. docblocks de ProjectVoter/UserVoter pour le
     * détail complet). Résumé en langage clair pour compléter la matrice.
     *
     * @return array<string, string> Groupe => résumé de la règle contextuelle
     */
    private function getContextualRules(): array
    {
        return [
            'Projets' => "Un rôle seul ne suffit pas : les actions de gestion (approuver une dépense, gérer une facture) exigent d'être Admin ET affecté au projet. Certaines actions sont bloquées si le projet est terminé ou suspendu, quel que soit le rôle.",
            'Utilisateurs' => "Auto-suppression toujours bloquée, quel que soit le rôle. Un compte Super Admin ne peut être modifié/supprimé que par un acteur actuellement élevé (élévation temporaire, cf. PAM) — un Super Admin \"au repos\" est protégé comme un Admin normal.",
        ];
    }

    /**
     * Comptes à privilèges élevés (rôle principal au-dessus de USER, mais en
     * dessous de SUPER_ADMIN) — liste de revue rapide, style "qui a plus que
     * le strict nécessaire ?" (cf. revue périodique des accès). Les Super
     * Admins sont volontairement exclus : ils passent par le PAM (élévation
     * temporaire tracée, cf. PrivilegeElevationController), pas par cette
     * liste de vigilance sur le personnel "en dessous".
     *
     * @param User[] $allUsers
     *
     * @return array<int, array{user: User, role: string, readRoute: string}>
     */
    private function getPrivilegedUsers(array $allUsers): array
    {
        $privileged = array_values(array_filter(
            $allUsers,
            static fn (User $user): bool => !\in_array($user->getPrimaryRole(), ['ROLE_USER', 'ROLE_SUPER_ADMIN'], true),
        ));

        usort($privileged, function (User $a, User $b): int {
            $rankA = array_search(str_replace('ROLE_', '', $a->getPrimaryRole()), self::ROLES, true);
            $rankB = array_search(str_replace('ROLE_', '', $b->getPrimaryRole()), self::ROLES, true);

            return $rankB <=> $rankA ?: strcasecmp($a->getFullName() ?? $a->getEmail(), $b->getFullName() ?? $b->getEmail());
        });

        return array_map(fn (User $user) => [
            'user' => $user,
            'role' => str_replace('ROLE_', '', $user->getPrimaryRole()),
            'readRoute' => $this->accountRouteFor($user, 'read'),
        ], $privileged);
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
