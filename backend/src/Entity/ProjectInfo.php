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

    // ===== Récit de l'étude de cas (prototype pCase) =====

    /** Résultat en une phrase (prototype `.res`), sous le titre de l'étude de cas. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $result = null;

    /** Version anglaise — retombe sur la version française côté frontend si vide. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $resultEn = null;

    /** « Le point de départ » : le contexte (1er paragraphe). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $context = null;

    /** Version anglaise — retombe sur la version française côté frontend si vide. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $contextEn = null;

    /** « Le point de départ » : le défi à relever (2e paragraphe). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $stake = null;

    /** Version anglaise — retombe sur la version française côté frontend si vide. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $stakeEn = null;

    /** « Ce qui a été livré » : paragraphe de synthèse. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $solution = null;

    /** Version anglaise — retombe sur la version française côté frontend si vide. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $solutionEn = null;

    /**
     * Bref en trois temps (situation, ce que j'ai fait, ce que cela a changé) : [{title, text}], 3 au plus.
     *
     * @var array<int, array{title: string, text: string}>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?array $brief = null;

    /**
     * Version anglaise — retombe sur la version française côté frontend si vide.
     *
     * @var array<int, array{title: string, text: string}>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?array $briefEn = null;

    /**
     * Démarche : étapes [{title, text}].
     *
     * @var array<int, array{title: string, text: string}>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?array $steps = null;

    /**
     * Version anglaise — retombe sur la version française côté frontend si vide.
     *
     * @var array<int, array{title: string, text: string}>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?array $stepsEn = null;

    /**
     * Livrables, un par entrée.
     *
     * @var string[]|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?array $deliverables = null;

    /**
     * Version anglaise — retombe sur la version française côté frontend si vide.
     *
     * @var string[]|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?array $deliverablesEn = null;

    /**
     * Légendes de la galerie, dans l'ordre des images.
     *
     * @var string[]|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?array $galleryCaptions = null;

    /**
     * Version anglaise — retombe sur la version française côté frontend si vide.
     *
     * @var string[]|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?array $galleryCaptionsEn = null;

    /**
     * Projet en production dont l'architecture et les protections ne sont pas
     * décrites publiquement : la fiche affiche la note « Pourquoi certains
     * détails ne sont pas publiés » du prototype.
     */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['api_public', 'api_admin'])]
    private bool $discreet = false;

    /** Slug de l'offre « Vous avez un besoin semblable ? » ; optionnel (cf. Offer::$proofProjectSlug). */
    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(max: 60)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D')]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $relatedOfferSlug = null;

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

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(?string $result): static
    {
        $this->result = $result;
        return $this;
    }

    public function getResultEn(): ?string
    {
        return $this->resultEn;
    }

    public function setResultEn(?string $resultEn): static
    {
        $this->resultEn = $resultEn;
        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): static
    {
        $this->context = $context;
        return $this;
    }

    public function getContextEn(): ?string
    {
        return $this->contextEn;
    }

    public function setContextEn(?string $contextEn): static
    {
        $this->contextEn = $contextEn;
        return $this;
    }

    public function getStake(): ?string
    {
        return $this->stake;
    }

    public function setStake(?string $stake): static
    {
        $this->stake = $stake;
        return $this;
    }

    public function getStakeEn(): ?string
    {
        return $this->stakeEn;
    }

    public function setStakeEn(?string $stakeEn): static
    {
        $this->stakeEn = $stakeEn;
        return $this;
    }

    public function getSolution(): ?string
    {
        return $this->solution;
    }

    public function setSolution(?string $solution): static
    {
        $this->solution = $solution;
        return $this;
    }

    public function getSolutionEn(): ?string
    {
        return $this->solutionEn;
    }

    public function setSolutionEn(?string $solutionEn): static
    {
        $this->solutionEn = $solutionEn;
        return $this;
    }

    /** @return array<int, array{title: string, text: string}> */
    public function getBrief(): array
    {
        return $this->brief ?? [];
    }

    /** @param array<int, array{title: string, text: string}>|null $brief */
    public function setBrief(?array $brief): static
    {
        $this->brief = $brief ?: null;
        return $this;
    }

    /** @return array<int, array{title: string, text: string}>|null */
    public function getBriefEn(): ?array
    {
        return $this->briefEn;
    }

    /** @param array<int, array{title: string, text: string}>|null $briefEn */
    public function setBriefEn(?array $briefEn): static
    {
        $this->briefEn = $briefEn ?: null;
        return $this;
    }

    /** @return array<int, array{title: string, text: string}> */
    public function getSteps(): array
    {
        return $this->steps ?? [];
    }

    /** @param array<int, array{title: string, text: string}>|null $steps */
    public function setSteps(?array $steps): static
    {
        $this->steps = $steps ?: null;
        return $this;
    }

    /** @return array<int, array{title: string, text: string}>|null */
    public function getStepsEn(): ?array
    {
        return $this->stepsEn;
    }

    /** @param array<int, array{title: string, text: string}>|null $stepsEn */
    public function setStepsEn(?array $stepsEn): static
    {
        $this->stepsEn = $stepsEn ?: null;
        return $this;
    }

    /** @return string[] */
    public function getDeliverables(): array
    {
        return $this->deliverables ?? [];
    }

    /** @param string[]|null $deliverables */
    public function setDeliverables(?array $deliverables): static
    {
        $this->deliverables = $deliverables ?: null;
        return $this;
    }

    /** @return string[]|null */
    public function getDeliverablesEn(): ?array
    {
        return $this->deliverablesEn;
    }

    /** @param string[]|null $deliverablesEn */
    public function setDeliverablesEn(?array $deliverablesEn): static
    {
        $this->deliverablesEn = $deliverablesEn ?: null;
        return $this;
    }

    /** @return string[] */
    public function getGalleryCaptions(): array
    {
        return $this->galleryCaptions ?? [];
    }

    /** @param string[]|null $galleryCaptions */
    public function setGalleryCaptions(?array $galleryCaptions): static
    {
        $this->galleryCaptions = $galleryCaptions ?: null;
        return $this;
    }

    /** @return string[]|null */
    public function getGalleryCaptionsEn(): ?array
    {
        return $this->galleryCaptionsEn;
    }

    /** @param string[]|null $galleryCaptionsEn */
    public function setGalleryCaptionsEn(?array $galleryCaptionsEn): static
    {
        $this->galleryCaptionsEn = $galleryCaptionsEn ?: null;
        return $this;
    }

    public function isDiscreet(): bool
    {
        return $this->discreet;
    }

    public function setDiscreet(bool $discreet): static
    {
        $this->discreet = $discreet;
        return $this;
    }

    public function getRelatedOfferSlug(): ?string
    {
        return $this->relatedOfferSlug;
    }

    public function setRelatedOfferSlug(?string $relatedOfferSlug): static
    {
        $this->relatedOfferSlug = $relatedOfferSlug;
        return $this;
    }
}
