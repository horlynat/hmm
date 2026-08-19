<?php

namespace App\Twig\Components\Admin;

use App\Entity\User;
use App\Repository\PermissionDefinitionRepository;
use App\Repository\RoleRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Carte en lecture seule « Permissions de ce compte » — répond à la fiche
 * compte d'un utilisateur en calculant, à partir de son rôle effectif, la
 * liste réelle des permissions accordées. Même source de données que
 * AdminSecurityRoleController::getDynamicPermissionMatrix() (PermissionDefinition/Role),
 * jamais une copie codée en dur : si une permission est modifiée depuis
 * /admin/security/permissions/, cette carte le reflète immédiatement.
 */
#[AsTwigComponent(
    name: 'admin:user_permissions_panel',
    template: 'components/admin/user_permissions_panel.html.twig'
)]
class UserPermissionsPanel
{
    public User $user;

    public function __construct(
        private readonly PermissionDefinitionRepository $permissionDefinitionRepository,
        private readonly RoleRepository $roleRepository,
    ) {
    }

    public function getPrimaryRole(): string
    {
        return $this->user->getPrimaryRole();
    }

    private function getPrimaryRoleRank(): int
    {
        return $this->roleRepository->findOneByCode($this->getPrimaryRole())?->getRank() ?? 0;
    }

    /**
     * Permissions du catalogue dynamique accordées au rôle effectif de ce
     * compte (rang de la permission <= rang du rôle — la hiérarchie linéaire
     * fait le reste, cf. role_hierarchy dans security.yaml).
     *
     * @return array<string, array<int, array{code: string, label: string}>>
     */
    public function getGrantedPermissions(): array
    {
        $rank = $this->getPrimaryRoleRank();
        $grouped = [];

        foreach ($this->permissionDefinitionRepository->findAllOrdered() as $definition) {
            if ($definition->getCurrentRole()->getRank() <= $rank) {
                $grouped[$definition->getCategory()][] = [
                    'code' => $definition->getCode(),
                    'label' => $definition->getLabel(),
                ];
            }
        }

        return $grouped;
    }

    /**
     * Au-delà du catalogue dynamique, ce compte est-il concerné par les
     * règles contextuelles (ABAC) de Projets/Utilisateurs ? À partir
     * d'Éditeur (peut être affecté à des projets) — un simple ROLE_USER
     * (client) n'a aucune action de gestion à ce niveau.
     */
    public function hasContextualRules(): bool
    {
        return $this->getPrimaryRoleRank() >= 1;
    }
}
