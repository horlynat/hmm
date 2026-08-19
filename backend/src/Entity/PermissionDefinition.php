<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Repository\PermissionDefinitionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Catalogue des permissions "métier" pilotables en base — seuil de rôle
 * minimum requis pour un code de permission donné (ex: ARTICLE_EDIT).
 *
 * Ne couvre QUE les Voters de pur seuil de rôle sans logique métier mêlée
 * (Article, Contact, Course, Dashboard, Finance, Quote, Skill, SupportTicket,
 * Testimonial — cf. AbstractRoleVoter). Volontairement absents de ce
 * catalogue : ProjectVoter/UserVoter (seuils mélangés à des garde-fous
 * critiques : auto-suppression, protection Super Admin, verrou d'état de
 * projet) et SecurityVoter/SettingsVoter (gouvernent la sécurité et les
 * réglages système eux-mêmes — les rendre éditables via l'interface qu'ils
 * protègent créerait un risque d'escalade de privilèges). Ces 4 Voters
 * restent des seuils codés en dur, jamais consultés via ce catalogue — cf.
 * PermissionRegistry::isOverridable().
 *
 * `defaultRole` est figé à la création (recopié du Voter d'origine au moment
 * du seed) et sert de valeur de réinitialisation — jamais modifié ensuite.
 * `currentRole` est la valeur réellement consultée à l'exécution
 * (PermissionRegistry), modifiable par un Super Administrateur.
 *
 * `defaultRole`/`currentRole` référencent App\Entity\Role (relation FK) plutôt
 * qu'une chaîne libre — le catalogue de rôles possibles est fermé (les 6
 * rôles Symfony existants, cf. Role) et une FK empêche toute valeur
 * incohérente en base, contrairement à une colonne string.
 */
#[ORM\Entity(repositoryClass: PermissionDefinitionRepository::class)]
#[ORM\Table(name: 'permission_definition')]
#[ORM\UniqueConstraint(columns: ['code'])]
#[ORM\HasLifecycleCallbacks]
class PermissionDefinition
{
    use CreatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_admin'])]
    private ?int $id = null;

    /** Code de l'attribut Voter, ex: "ARTICLE_EDIT". */
    #[ORM\Column(length: 100)]
    #[Groups(['api_admin'])]
    private string $code;

    /** Libellé humain affiché dans l'interface admin, ex: "Modifier un article". */
    #[ORM\Column(length: 255)]
    #[Groups(['api_admin'])]
    private string $label;

    /** Regroupement d'affichage, ex: "Articles" — même découpage que la page Rôles. */
    #[ORM\Column(length: 100)]
    #[Groups(['api_admin'])]
    private string $category;

    /** Rôle d'origine (celui codé en dur dans le Voter au moment du seed) — valeur de réinitialisation. */
    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(name: 'default_role_id', nullable: false)]
    #[Groups(['api_admin'])]
    private Role $defaultRole;

    /** Rôle réellement appliqué à l'exécution — seul champ modifiable par l'admin. */
    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(name: 'current_role_id', nullable: false)]
    #[Groups(['api_admin'])]
    private Role $currentRole;

    #[ORM\Column(nullable: true)]
    #[Groups(['api_admin'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['api_admin'])]
    private ?User $updatedBy = null;

    public function __construct(string $code, string $label, string $category, Role $defaultRole)
    {
        $this->code = $code;
        $this->label = $label;
        $this->category = $category;
        $this->defaultRole = $defaultRole;
        $this->currentRole = $defaultRole;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getDefaultRole(): Role
    {
        return $this->defaultRole;
    }

    public function setDefaultRole(Role $defaultRole): self
    {
        $this->defaultRole = $defaultRole;

        return $this;
    }

    public function getCurrentRole(): Role
    {
        return $this->currentRole;
    }

    public function setCurrentRole(Role $currentRole): self
    {
        $this->currentRole = $currentRole;

        return $this;
    }

    public function isOverridden(): bool
    {
        return $this->currentRole !== $this->defaultRole;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }
}
