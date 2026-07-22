<?php

namespace App\Entity;

use App\Entity\Media;
use App\Entity\ProjectExpense;
use App\Entity\ProjectHistory;
use App\Entity\Skill;
use App\Entity\Tag;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\SlugTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\User;
use App\Enum\BillingTypeEnum;
use App\Enum\ProjectPriorityEnum;
use App\Enum\ProjectStatusEnum;
use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[UniqueEntity(fields: ['slug'], message: "Ce slug est déjà utilisé pour un autre projet.")]
#[ORM\HasLifecycleCallbacks]
class Project
{
    use SlugTrait;
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_public', 'api_admin'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire.")]
    #[Assert\Length(min: 3, max: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "La description est obligatoire.")]
    #[Assert\Length(min: 20)]
    #[Groups(['api_public', 'api_admin'])]
    private string $description = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le lien est obligatoire.")]
    #[Assert\Url(message: "Le lien doit être une URL valide.")]
    #[Groups(['api_public', 'api_admin'])]
    private string $link = '';

    #[ORM\Column(type: 'string', enumType: ProjectStatusEnum::class)]
    #[Groups(['api_public', 'api_admin'])]
    private ProjectStatusEnum $status;

    #[ORM\Column(type: 'string', enumType: ProjectPriorityEnum::class, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?ProjectPriorityEnum $priority = null;

    #[ORM\Column(type: 'string', enumType: BillingTypeEnum::class, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?BillingTypeEnum $billingType = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\Range(min: 0, max: 100, notInRangeMessage: "L'avancement doit être compris entre {{ min }}% et {{ max }}%.")]
    #[Groups(['api_public', 'api_admin'])]
    private int $progress = 0;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    #[Assert\PositiveOrZero(message: "Le budget doit être positif ou nul.")]
    #[Groups(['api_admin'])]
    private string $budget = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    #[Groups(['api_admin'])]
    private string $spent = '0.00';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['api_admin'])]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['api_admin'])]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'ownedProjects')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['api_admin'])]
    private User $owner;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'collaboratingProjects')]
    #[Groups(['api_admin'])]
    private Collection $collaborators;

    /** @var Collection<int, ProjectHistory> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: ProjectHistory::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $histories;

    /** @var Collection<int, ProjectExpense> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: ProjectExpense::class, cascade: ['persist'], orphanRemoval: true)]
    #[Groups(['api_admin'])]
    private Collection $expenses;

    /** @var Collection<int, Skill> */
    #[ORM\ManyToMany(targetEntity: Skill::class, inversedBy: 'projects')]
    #[Groups(['api_admin'])]
    private Collection $skills;

    /** @var Collection<int, Media> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Media::class, cascade: ['persist'], orphanRemoval: true)]
    #[Groups(["api_detailed"])]
    private Collection $media;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'client_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['api_admin'])]
    private ?User $client = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['api_admin'])]
    private ?\DateTimeImmutable $deadline = null;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'projects')]
    private Collection $tags;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = ProjectStatusEnum::UPCOMING;
        $this->skills = new ArrayCollection();
        $this->media = new ArrayCollection();
        $this->collaborators = new ArrayCollection();
        $this->histories = new ArrayCollection();
        $this->expenses = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    // ========== Getters et Setters Globaux ==========

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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function setLink(string $link): static
    {
        $this->link = $link;
        return $this;
    }

    /**
     * @return Collection<int, Skill>
     */
    public function getSkills(): Collection
    {
        return $this->skills;
    }

    public function addSkill(Skill $skill): static
    {
        if (!$this->skills->contains($skill)) {
            $this->skills->add($skill);
        }
        return $this;
    }

    public function removeSkill(Skill $skill): static
    {
        $this->skills->removeElement($skill);
        return $this;
    }

    /**
     * @return Collection<int, Media>
     */
    public function getMedia(): Collection
    {
        return $this->media;
    }

    public function addMedia(Media $media): static
    {
        if (!$this->media->contains($media)) {
            $this->media->add($media);
            $media->setProject($this);
        }
        return $this;
    }

    public function removeMedia(Media $media): static
    {
        if ($this->media->removeElement($media)) {
            if ($media->getProject() === $this) {
                $media->setProject(null);
            }
        }
        return $this;
    }

    public function getStatus(): ProjectStatusEnum
    {
        return $this->status;
    }

    public function setStatus(ProjectStatusEnum $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getPriority(): ?ProjectPriorityEnum
    {
        return $this->priority;
    }

    public function setPriority(?ProjectPriorityEnum $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function getBillingType(): ?BillingTypeEnum
    {
        return $this->billingType;
    }

    public function setBillingType(?BillingTypeEnum $billingType): static
    {
        $this->billingType = $billingType;
        return $this;
    }

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function setProgress(int $progress): static
    {
        $this->progress = $progress;
        return $this;
    }

    public function getBudget(): string
    {
        return $this->budget;
    }

    public function setBudget(string $budget): static
    {
        $this->budget = $budget;
        return $this;
    }

    public function getSpent(): string
    {
        return $this->spent;
    }

    public function setSpent(string $spent): static
    {
        $this->spent = $spent;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getCollaborators(): Collection
    {
        return $this->collaborators;
    }

    public function addCollaborator(User $collaborator): static
    {
        if (!$this->collaborators->contains($collaborator)) {
            $this->collaborators->add($collaborator);
            $collaborator->addCollaboratingProject($this);
        }
        return $this;
    }

    public function removeCollaborator(User $collaborator): static
    {
        if ($this->collaborators->removeElement($collaborator)) {
            $collaborator->removeCollaboratingProject($this);
        }
        return $this;
    }

    /**
     * @return Collection<int, ProjectHistory>
     */
    public function getHistories(): Collection
    {
        return $this->histories;
    }

    public function addHistory(ProjectHistory $history): static
    {
        if (!$this->histories->contains($history)) {
            $this->histories->add($history);
            $history->setProject($this);
        }
        return $this;
    }

    public function removeHistory(ProjectHistory $history): static
    {
        $this->histories->removeElement($history);
        return $this;
    }

    /**
     * @return Collection<int, ProjectExpense>
     */
    public function getExpenses(): Collection
    {
        return $this->expenses;
    }

    public function addExpense(ProjectExpense $expense): static
    {
        if (!$this->expenses->contains($expense)) {
            $this->expenses->add($expense);
            $expense->setProject($this);
        }
        return $this;
    }

    public function removeExpense(ProjectExpense $expense): static
    {
        $this->expenses->removeElement($expense);
        return $this;
    }

    public function getClient(): ?User
    {
        return $this->client;
    }

    public function setClient(?User $client): static
    {
        $this->client = $client;
        return $this;
    }

    // ========== Méthodes Utilitaires & Business Logique ==========

    public function getStatusLabel(): string
    {
        return $this->status->getLabel();
    }

    public function getStatusBadgeClass(): string
    {
        return $this->status->getBadgeClass();
    }

    /**
     * Récupère le libellé du type de facturation si défini.
     */
    public function getBillingTypeLabel(): ?string
    {
        return $this->billingType ? $this->billingType->getLabel() : 'Non défini';
    }

    /**
     * Récupère la classe CSS pour le badge de facturation si définie.
     */
    public function getBillingTypeBadgeClass(): string
    {
        return $this->billingType ? $this->billingType->getBadgeClass() : 'bg-secondary text-white';
    }

    public function getRemainingBudget(): string
    {
        return bcsub($this->budget, $this->spent, 2);
    }

    public function isOverBudget(): bool
    {
        return bccomp($this->spent, $this->budget, 2) > 0;
    }

    public function getFormattedBudget(): string
    {
        return number_format((float) $this->budget, 2, ',', ' ') . ' €';
    }

    public function getFormattedSpent(): string
    {
        return number_format((float) $this->spent, 2, ',', ' ') . ' €';
    }

    public function getFormattedRemainingBudget(): string
    {
        return number_format((float) $this->getRemainingBudget(), 2, ',', ' ') . ' €';
    }

    public function addToHistory(string $action, User $user, ?string $details = null): static
    {
        $history = new ProjectHistory();
        $history
            ->setProject($this)
            ->setAction($action)
            ->setUser($user)
            ->setDetails($details);

        $this->histories->add($history);
        return $this;
    }

    public function logCreation(User $user): static
    {
        return $this->addToHistory('created', $user, 'Projet créé');
    }

    /** @param array<string, mixed> $changes */
    public function logUpdate(User $user, array $changes = []): static
    {
        $details = !empty($changes)
            ? 'Champs modifiés : ' . implode(', ', array_keys($changes))
            : 'Mise à jour du projet';
        return $this->addToHistory('updated', $user, $details);
    }

    public function logStatusChange(User $user, string $oldStatus, string $newStatus): static
    {
        return $this->addToHistory(
            'status_changed',
            $user,
            sprintf('Statut changé de "%s" à "%s"', $oldStatus, $newStatus)
        );
    }

    public function addProjectExpense(string $amount, string $description, User $user): static
    {
        $expense = new ProjectExpense();
        $expense
            ->setAmount($amount)
            ->setDescription($description)
            ->setProject($this)
            ->setUser($user)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->expenses->add($expense);
        $this->spent = bcadd($this->spent, $amount, 2);

        $this->addToHistory('expense_added', $user, sprintf(
            'Dépense ajoutée: %s - %s',
            number_format((float) $amount, 2, ',', ' ') . ' €',
            $description
        ));

        return $this;
    }

    public function removeProjectExpense(ProjectExpense $expense): static
    {
        if ($this->expenses->removeElement($expense)) {
            $this->spent = bcsub($this->spent, $expense->getAmount(), 2);

            $this->addToHistory('expense_removed', $expense->getUser(), sprintf(
                'Dépense supprimée: %s - %s',
                number_format((float) $expense->getAmount(), 2, ',', ' ') . ' €',
                $expense->getDescription() ?? 'Sans description'
            ));
        }
        return $this;
    }

    public function logCollaboratorAdded(User $user, User $collaborator): static
    {
        return $this->addToHistory(
            'collaborator_added',
            $user,
            sprintf('Collaborateur ajouté : %s', $collaborator->getEmail())
        );
    }

    public function logCollaboratorRemoved(User $user, User $collaborator): static
    {
        return $this->addToHistory(
            'collaborator_removed',
            $user,
            sprintf('Collaborateur retiré : %s', $collaborator->getEmail())
        );
    }

    public function getBudgetPercentageUsed(): float
    {
        if ($this->budget === '0.00') {
            return 0.0;
        }
        return min(100.0, (float) bccomp($this->spent, $this->budget, 2) * 100);
    }

    public function getBudgetStatus(): string
    {
        $percentage = $this->getBudgetPercentageUsed();

        if ($percentage >= 100) {
            return 'Dépassé';
        } elseif ($percentage >= 80) {
            return 'Attention';
        } elseif ($percentage >= 50) {
            return 'En cours';
        } else {
            return 'Sous contrôle';
        }
    }

    public function getBudgetStatusBadgeClass(): string
    {
        $percentage = $this->getBudgetPercentageUsed();

        if ($percentage >= 100) {
            return 'bg-red-500 text-white';
        } elseif ($percentage >= 80) {
            return 'bg-yellow-500 text-black';
        } elseif ($percentage >= 50) {
            return 'bg-blue-500 text-white';
        } else {
            return 'bg-green-500 text-white';
        }
    }

    /**
     * @return array{totalBudget: string, totalSpent: string, totalProjects: int, overBudgetCount: int, lowBudgetCount: int, remainingBudget: string}
     */
    public static function getBudgetStatistics(EntityManagerInterface $entityManager): array
    {
        $query = $entityManager->createQuery(
            'SELECT
                SUM(p.budget) as totalBudget,
                SUM(p.spent) as totalSpent,
                COUNT(p.id) as totalProjects,
                SUM(CASE WHEN p.spent > p.budget THEN 1 ELSE 0 END) as overBudgetCount,
                SUM(CASE WHEN p.budget > 0 AND (p.budget - p.spent) / p.budget < 0.1 THEN 1 ELSE 0 END) as lowBudgetCount
             FROM App\Entity\Project p'
        );

        $result = $query->getSingleResult();

        $totalBudget = $result['totalBudget'] ?? '0.00';
        $totalSpent  = $result['totalSpent'] ?? '0.00';

        return [
            'totalBudget'     => (string) $totalBudget,
            'totalSpent'      => (string) $totalSpent,
            'totalProjects'   => (int) ($result['totalProjects'] ?? 0),
            'overBudgetCount' => (int) ($result['overBudgetCount'] ?? 0),
            'lowBudgetCount'  => (int) ($result['lowBudgetCount'] ?? 0),
            'remainingBudget' => bcsub((string) $totalBudget, (string) $totalSpent, 2),
        ];
    }

    public function getDeadline(): ?\DateTimeImmutable
    {
        return $this->deadline;
    }

    // ===== Méthodes pour la gestion de la Deadline =====

    /**
     * Vérifie si le projet est en retard par rapport à sa deadline.
     */
    public function isPastDeadline(): bool
    {
        if (!$this->deadline || $this->status === ProjectStatusEnum::COMPLETED) {
            return false;
        }

        return $this->deadline < new \DateTimeImmutable();
    }

    /**
     * Récupère le nombre de jours restants (ou de retard si négatif).
     */
    public function getDaysRemaining(): ?int
    {
        if (!$this->deadline) {
            return null;
        }

        $now = new \DateTimeImmutable('today');
        $deadlineDay = \DateTimeImmutable::createFromInterface($this->deadline)->setTime(0, 0, 0);
        
        $interval = $now->diff($deadlineDay);
        
        return (int) $interval->format('%r%a');
    }

    /**
     * Formate proprement la date de la deadline pour l'affichage.
     */
    public function getFormattedDeadline(string $format = 'd/m/Y'): ?string
    {
        return $this->deadline ? $this->deadline->format($format) : null;
    }

    public function setDeadline(?\DateTimeImmutable $deadline): static
    {
        $this->deadline = $deadline;

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }
}