<?php

namespace App\Entity;

use App\Entity\Media;
use App\Entity\Project;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contenu "vitrine" d'un projet (rôle, stack, défis/solutions, résultats,
 * preuves visuelles) — sépare le récit éditorial public des données de
 * gestion portées par Project (budget, deadline, équipe, dépenses...).
 */
#[ORM\Entity]
#[ORM\Table(name: 'project_info')]
class ProjectInfo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_public', 'api_admin'])]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'info', targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $role = null;

    /** Rôle en anglais — optionnel, retombe sur `role` (FR) côté frontend si vide. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $roleEn = null;

    /** @var string[] */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['api_public', 'api_admin'])]
    private array $objectives = [];

    /** Version anglaise — optionnelle, retombe sur `objectives` (FR) côté frontend si vide. @var string[] */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?array $objectivesEn = null;

    /** @var array<int, array{name: string, rationale: ?string}> */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['api_public', 'api_admin'])]
    private array $techStack = [];

    /** Version anglaise — optionnelle, retombe sur `techStack` (FR) côté frontend si vide. @var array<int, array{name: string, rationale: ?string}>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?array $techStackEn = null;

    /** @var array<int, array{problem: string, solution: string}> */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['api_public', 'api_admin'])]
    private array $challenges = [];

    /** Version anglaise — optionnelle, retombe sur `challenges` (FR) côté frontend si vide. @var array<int, array{problem: string, solution: string}>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?array $challengesEn = null;

    /** @var array<int, array{label: string, value: string}> */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['api_public', 'api_admin'])]
    private array $results = [];

    /** Version anglaise — optionnelle, retombe sur `results` (FR) côté frontend si vide. @var array<int, array{label: string, value: string}>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?array $resultsEn = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: "Le lien du dépôt doit être une URL valide.")]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $repoUrl = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(name: 'cover_image_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['api_public', 'api_admin'])]
    private ?Media $coverImage = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(name: 'architecture_diagram_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['api_public', 'api_admin'])]
    private ?Media $architectureDiagram = null;

    // ===== Getters et Setters =====

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function setProject(Project $project): static
    {
        $this->project = $project;
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getRoleEn(): ?string
    {
        return $this->roleEn;
    }

    public function setRoleEn(?string $roleEn): static
    {
        $this->roleEn = $roleEn;
        return $this;
    }

    /** @return string[] */
    public function getObjectives(): array
    {
        return $this->objectives;
    }

    /** @param string[] $objectives */
    public function setObjectives(array $objectives): static
    {
        $this->objectives = $objectives;
        return $this;
    }

    /** @return string[]|null */
    public function getObjectivesEn(): ?array
    {
        return $this->objectivesEn;
    }

    /** @param string[]|null $objectivesEn */
    public function setObjectivesEn(?array $objectivesEn): static
    {
        $this->objectivesEn = $objectivesEn;
        return $this;
    }

    public function getTechStack(): array
    {
        return $this->techStack;
    }

    public function setTechStack(array $techStack): static
    {
        $this->techStack = $techStack;
        return $this;
    }

    public function getTechStackEn(): ?array
    {
        return $this->techStackEn;
    }

    public function setTechStackEn(?array $techStackEn): static
    {
        $this->techStackEn = $techStackEn;
        return $this;
    }

    public function getChallenges(): array
    {
        return $this->challenges;
    }

    public function setChallenges(array $challenges): static
    {
        $this->challenges = $challenges;
        return $this;
    }

    public function getChallengesEn(): ?array
    {
        return $this->challengesEn;
    }

    public function setChallengesEn(?array $challengesEn): static
    {
        $this->challengesEn = $challengesEn;
        return $this;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function setResults(array $results): static
    {
        $this->results = $results;
        return $this;
    }

    public function getResultsEn(): ?array
    {
        return $this->resultsEn;
    }

    public function setResultsEn(?array $resultsEn): static
    {
        $this->resultsEn = $resultsEn;
        return $this;
    }

    public function getRepoUrl(): ?string
    {
        return $this->repoUrl;
    }

    public function setRepoUrl(?string $repoUrl): static
    {
        $this->repoUrl = $repoUrl;
        return $this;
    }

    public function getCoverImage(): ?Media
    {
        return $this->coverImage;
    }

    public function setCoverImage(?Media $coverImage): static
    {
        $this->coverImage = $coverImage;
        return $this;
    }

    public function getArchitectureDiagram(): ?Media
    {
        return $this->architectureDiagram;
    }

    public function setArchitectureDiagram(?Media $architectureDiagram): static
    {
        $this->architectureDiagram = $architectureDiagram;
        return $this;
    }
}
