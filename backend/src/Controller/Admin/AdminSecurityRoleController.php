<?php

namespace App\Controller\Admin;

use App\Repository\UserRepository;
use App\Security\Voter\SecurityVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page de lecture de la hiérarchie des rôles et de la matrice de permissions.
 *
 * Ce contrôleur n'applique aucune règle d'autorisation : il se contente
 * d'afficher ce que `security.yaml` (role_hierarchy) et les Voters
 * appliquent déjà (cf. _config.backend.md, section "Matrice complète
 * rôles × permissions").
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
            'roleCounts' => $this->countUsersByRole($userRepository),
        ]);
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
                'DASHBOARD_EXPORT' => 3,
            ],
        ];
    }

    /**
     * @return array<string, int> Rôle => nombre de comptes (chaque compte compté une fois, sous son rôle le plus élevé)
     */
    private function countUsersByRole(UserRepository $userRepository): array
    {
        $counts = array_fill_keys(self::ROLES, 0);

        foreach ($userRepository->findAll() as $user) {
            $userRoles = $user->getRoles();
            foreach (array_reverse(self::ROLES) as $role) {
                if (\in_array('ROLE_' . $role, $userRoles, true)) {
                    ++$counts[$role];
                    break;
                }
            }
        }

        return $counts;
    }
}
