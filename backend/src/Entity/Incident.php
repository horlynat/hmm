<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Enum\IncidentCategoryEnum;
use App\Enum\IncidentSeverityEnum;
use App\Enum\IncidentStatusEnum;
use App\Repository\IncidentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Journal d'incidents (pannes, quasi-incidents, découvertes en cours
 * d'intervention) — pas un doublon des runbooks Markdown
 * (docs/incident-auth.md, docs/incident-data-loss.md) : ceux-ci documentent
 * "que faire quand X arrive", cette entité trace "qu'est-ce qui EST arrivé,
 * quand, et combien de fois" — le seul moyen de voir une récurrence sans
 * relire tout l'historique Git/mails à chaque fois (cf.
 * IncidentRepository::countByCategory).
 *
 * $category et $severity sont indépendants : un incident peut être
 * catégorisé DEPLOYMENT et gravité CRITICAL, ou INFRASTRUCTURE et gravité
 * LOW — la catégorie sert au regroupement, la gravité à la priorisation.
 */
#[ORM\Entity(repositoryClass: IncidentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Incident
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_admin'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['api_admin'])]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 255)]
    private string $title = '';

    #[ORM\Column(length: 30, enumType: IncidentCategoryEnum::class)]
    #[Groups(['api_admin'])]
    private IncidentCategoryEnum $category = IncidentCategoryEnum::OTHER;

    #[ORM\Column(length: 20, enumType: IncidentSeverityEnum::class)]
    #[Groups(['api_admin'])]
    private IncidentSeverityEnum $severity = IncidentSeverityEnum::MEDIUM;

    #[ORM\Column(length: 20, enumType: IncidentStatusEnum::class)]
    #[Groups(['api_admin'])]
    private IncidentStatusEnum $status = IncidentStatusEnum::OPEN;

    /** Ce qui s'est passé, en langage factuel — pas d'interprétation ici. */
    #[ORM\Column(type: 'text')]
    #[Groups(['api_admin'])]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    private string $description = '';

    /** Pourquoi c'est arrivé — peut rester vide tant que l'analyse n'est pas faite. */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['api_admin'])]
    private ?string $rootCause = null;

    /** Ce qui a été fait (ou reste à faire) pour corriger/éviter la récidive. */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['api_admin'])]
    private ?string $remediation = null;

    /**
     * Référence libre vers la trace concrète du correctif — numéro de PR
     * ("horlynat/hmm#95"), chemin de doc (docs/incident-auth.md#2), commit...
     * Volontairement une simple chaîne (pas de FK) : ces références pointent
     * vers des systèmes externes à cette base (GitHub, Markdown versionné).
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_admin'])]
    private ?string $relatedReference = null;

    // Non nullable (colonne NOT NULL) : initialisée à "maintenant" par défaut
    // -- le contrôleur/formulaire peut la corriger, mais elle n'est jamais
    // vide, contrairement à createdAt (fixée au flush) qui, elle, représente
    // le moment de la saisie plutôt que celui de l'incident lui-même.
    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['api_admin'])]
    #[Assert\NotNull(message: 'La date de détection est obligatoire.')]
    private \DateTimeImmutable $detectedAt;

    public function __construct()
    {
        $this->detectedAt = new \DateTimeImmutable();
    }

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['api_admin'])]
    private ?\DateTimeImmutable $resolvedAt = null;

    /** Nullable : ne doit jamais bloquer la suppression d'un compte admin. */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['api_admin'])]
    private ?User $reportedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getCategory(): IncidentCategoryEnum
    {
        return $this->category;
    }

    public function setCategory(IncidentCategoryEnum $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getSeverity(): IncidentSeverityEnum
    {
        return $this->severity;
    }

    public function setSeverity(IncidentSeverityEnum $severity): static
    {
        $this->severity = $severity;

        return $this;
    }

    public function getStatus(): IncidentStatusEnum
    {
        return $this->status;
    }

    public function setStatus(IncidentStatusEnum $status): static
    {
        $this->status = $status;

        if (IncidentStatusEnum::RESOLVED === $status && null === $this->resolvedAt) {
            $this->resolvedAt = new \DateTimeImmutable();
        }
        // Rouvrir (OPEN/MONITORING) efface la date de résolution précédente
        // — un incident redevenu actif n'est plus "résolu depuis le X".
        if (IncidentStatusEnum::RESOLVED !== $status) {
            $this->resolvedAt = null;
        }

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getRootCause(): ?string
    {
        return $this->rootCause;
    }

    public function setRootCause(?string $rootCause): static
    {
        $this->rootCause = $rootCause;

        return $this;
    }

    public function getRemediation(): ?string
    {
        return $this->remediation;
    }

    public function setRemediation(?string $remediation): static
    {
        $this->remediation = $remediation;

        return $this;
    }

    public function getRelatedReference(): ?string
    {
        return $this->relatedReference;
    }

    public function setRelatedReference(?string $relatedReference): static
    {
        $this->relatedReference = $relatedReference;

        return $this;
    }

    public function getDetectedAt(): \DateTimeImmutable
    {
        return $this->detectedAt;
    }

    public function setDetectedAt(\DateTimeImmutable $detectedAt): static
    {
        $this->detectedAt = $detectedAt;

        return $this;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function getReportedBy(): ?User
    {
        return $this->reportedBy;
    }

    public function setReportedBy(?User $reportedBy): static
    {
        $this->reportedBy = $reportedBy;

        return $this;
    }

    public function isOpen(): bool
    {
        return IncidentStatusEnum::RESOLVED !== $this->status;
    }
}
