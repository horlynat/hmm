<?php

namespace App\Entity;

use App\Entity\Invoice;
use App\Entity\Media;
use App\Entity\ProjectExpense;
use App\Entity\ProjectHistory;
use App\Entity\ProjectInfo;
use App\Entity\Skill;
use App\Entity\Tag;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\SlugTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\User;
use App\Enum\BillingTypeEnum;
use App\Enum\InvoiceStatusEnum;
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

    /** Titre en anglais — optionnel, retombe sur `title` (FR) côté frontend si vide. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $titleEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "La description est obligatoire.")]
    #[Assert\Length(min: 20)]
    #[Groups(['api_public', 'api_admin'])]
    private string $description = '';

    /** Description en anglais — optionnel, retombe sur `description` (FR) côté frontend si vide. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $descriptionEn = null;

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

    /** @var Collection<int, Invoice> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Invoice::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['issuedAt' => 'DESC'])]
    #[Groups(['api_admin'])]
    private Collection $invoices;

    /** @var Collection<int, ProjectTask> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: ProjectTask::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    #[Groups(['api_admin'])]
    private Collection $tasks;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Comment::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $comments;

    /** @var Collection<int, TimeEntry> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: TimeEntry::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['spentOn' => 'DESC', 'id' => 'DESC'])]
    private Collection $timeEntries;

    /** @var Collection<int, Skill> */
    #[ORM\ManyToMany(targetEntity: Skill::class, inversedBy: 'projects')]
    #[Groups(['api_admin'])]
    private Collection $skills;

    /** @var Collection<int, Media> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Media::class, cascade: ['persist'], orphanRemoval: true)]
    #[Groups(["api_public", "api_detailed"])]
    private Collection $media;

    #[ORM\OneToOne(mappedBy: 'project', targetEntity: ProjectInfo::class, cascade: ['persist', 'remove'])]
    #[Groups(['api_public'])]
    private ?ProjectInfo $info = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'client_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['api_admin'])]
    private ?User $client = null;

    /** Devis à l'origine de ce projet, s'il a été créé via "Convertir en projet". */
    #[ORM\OneToOne(inversedBy: 'convertedProject', targetEntity: QuoteRequest::class)]
    #[ORM\JoinColumn(name: 'source_quote_request_id', nullable: true, unique: true, onDelete: 'SET NULL')]
    #[Groups(['api_admin'])]
    private ?QuoteRequest $sourceQuoteRequest = null;

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
        $this->invoices = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->tasks = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->timeEntries = new ArrayCollection();
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

    public function getTitleEn(): ?string
    {
        return $this->titleEn;
    }

    public function setTitleEn(?string $titleEn): static
    {
        $this->titleEn = $titleEn;
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

    public function getDescriptionEn(): ?string
    {
        return $this->descriptionEn;
    }

    public function setDescriptionEn(?string $descriptionEn): static
    {
        $this->descriptionEn = $descriptionEn;
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

    public function getInfo(): ?ProjectInfo
    {
        return $this->info;
    }

    public function setInfo(?ProjectInfo $info): static
    {
        if ($info !== null) {
            $info->setProject($this);
        }
        $this->info = $info;
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

    /**
     * @return Collection<int, Invoice>
     */
    public function getInvoices(): Collection
    {
        return $this->invoices;
    }

    public function addInvoice(Invoice $invoice): static
    {
        if (!$this->invoices->contains($invoice)) {
            $this->invoices->add($invoice);
            $invoice->setProject($this);
        }
        return $this;
    }

    public function removeInvoice(Invoice $invoice): static
    {
        $this->invoices->removeElement($invoice);
        return $this;
    }

    public function getUnpaidInvoicesTotal(): string
    {
        $total = '0.00';
        foreach ($this->invoices as $invoice) {
            if (!$invoice->isPaid() && InvoiceStatusEnum::CANCELLED !== $invoice->getStatus()) {
                $total = bcadd($total, $invoice->getAmount(), 2);
            }
        }
        return $total;
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

    public function getSourceQuoteRequest(): ?QuoteRequest
    {
        return $this->sourceQuoteRequest;
    }

    public function setSourceQuoteRequest(?QuoteRequest $sourceQuoteRequest): static
    {
        $this->sourceQuoteRequest = $sourceQuoteRequest;
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

    /** Solde budgétaire disponible pour de nouvelles dépenses approuvées (budget − dépensé). */
    public function getAvailableBudget(): string
    {
        return bcsub($this->budget, $this->spent, 2);
    }

    /**
     * Recalcule `spent` comme la somme des dépenses APPROUVÉES — source de vérité.
     * À appeler après tout changement de statut/montant/suppression d'une dépense.
     */
    public function recalculateSpent(): static
    {
        $total = '0.00';
        foreach ($this->expenses as $expense) {
            if ($expense->isApproved()) {
                $total = bcadd($total, $expense->getAmount(), 2);
            }
        }
        $this->spent = $total;
        return $this;
    }

    /** Montant des dépenses en attente d'approbation (engagé mais pas encore décompté). */
    public function getPendingExpensesTotal(): string
    {
        $total = '0.00';
        foreach ($this->expenses as $expense) {
            if ($expense->isPending()) {
                $total = bcadd($total, $expense->getAmount(), 2);
            }
        }
        return $total;
    }

    public function hasPendingExpenses(): bool
    {
        return bccomp($this->getPendingExpensesTotal(), '0.00', 2) > 0;
    }

    public function isOverBudget(): bool
    {
        return bccomp($this->spent, $this->budget, 2) > 0;
    }

    /** Le budget est-il renseigné (> 0) ? Prérequis à toute dépense. */
    public function hasBudget(): bool
    {
        return bccomp($this->budget, '0.00', 2) > 0;
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

    /**
     * Soumet une dépense (statut PENDING par défaut) : elle n'impacte PAS `spent`
     * tant qu'elle n'est pas approuvée (cf. App\Service\ExpenseWorkflow). Ne touche
     * jamais `spent` directement — le décompte reste dérivé des dépenses approuvées.
     */
    public function addProjectExpense(string $amount, string $description, User $user): static
    {
        $expense = new ProjectExpense();
        $expense
            ->setAmount($amount)
            ->setDescription($description)
            ->setUser($user);

        $this->attachExpense($expense, $user);
        return $this;
    }

    /** Attache une dépense déjà construite (PENDING) au projet + journalisation. */
    public function attachExpense(ProjectExpense $expense, User $user): static
    {
        $expense->setProject($this);
        if (!$this->expenses->contains($expense)) {
            $this->expenses->add($expense);
        }

        $this->addToHistory('expense_submitted', $user, sprintf(
            'Dépense soumise: %s (%s) - %s',
            $expense->getFormattedAmount(),
            $expense->getCategory()->getLabel(),
            $expense->getDescription() ?? 'sans description',
        ));

        return $this;
    }

    public function removeProjectExpense(ProjectExpense $expense): static
    {
        if ($this->expenses->removeElement($expense)) {
            // `spent` est recalculé depuis les dépenses approuvées restantes.
            $this->recalculateSpent();

            $this->addToHistory('expense_removed', $expense->getUser(), sprintf(
                'Dépense supprimée: %s - %s',
                $expense->getFormattedAmount(),
                $expense->getDescription() ?? 'Sans description'
            ));
        }
        return $this;
    }

    // ========== Tâches / jalons ==========

    /** @return Collection<int, ProjectTask> */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(ProjectTask $task): static
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->setProject($this);
        }
        $this->recalculateProgress();
        return $this;
    }

    public function removeTask(ProjectTask $task): static
    {
        if ($this->tasks->removeElement($task)) {
            $this->recalculateProgress();
        }
        return $this;
    }

    public function getTasksCount(): int
    {
        return $this->tasks->count();
    }

    public function getDoneTasksCount(): int
    {
        return $this->tasks->filter(static fn (ProjectTask $t) => $t->isDone())->count();
    }

    /** Reste-t-il des tâches non terminées ? (garde-fou de clôture) */
    public function hasOpenTasks(): bool
    {
        return $this->getDoneTasksCount() < $this->getTasksCount();
    }

    /**
     * Dérive `progress` du ratio de tâches terminées dès qu'au moins une tâche
     * existe. Sans tâche, la valeur manuelle est conservée.
     */
    public function recalculateProgress(): static
    {
        $total = $this->getTasksCount();
        if ($total > 0) {
            $this->progress = (int) round(100 * $this->getDoneTasksCount() / $total);
        }
        return $this;
    }

    // ========== Commentaires ==========

    /** @return Collection<int, Comment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setProject($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        $this->comments->removeElement($comment);

        return $this;
    }

    // ========== Suivi du temps ==========

    /** @return Collection<int, TimeEntry> */
    public function getTimeEntries(): Collection
    {
        return $this->timeEntries;
    }

    public function addTimeEntry(TimeEntry $entry): static
    {
        if (!$this->timeEntries->contains($entry)) {
            $this->timeEntries->add($entry);
            $entry->setProject($this);
        }

        return $this;
    }

    public function removeTimeEntry(TimeEntry $entry): static
    {
        $this->timeEntries->removeElement($entry);

        return $this;
    }

    /** Total du temps passé (minutes), toutes saisies confondues. */
    public function getTotalMinutes(): int
    {
        $total = 0;
        foreach ($this->timeEntries as $entry) {
            $total += $entry->getMinutes();
        }

        return $total;
    }

    /** Total du temps passé, formaté « 12 h 30 ». */
    public function getFormattedTotalTime(): string
    {
        $minutes = $this->getTotalMinutes();
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        if (0 === $h) {
            return $m.' min';
        }

        return 0 === $m ? $h.' h' : sprintf('%d h %02d', $h, $m);
    }

    // ========== Équipe ==========

    /** L'utilisateur fait-il partie de l'équipe du projet (owner ou collaborateur) ? */
    public function isTeamMember(User $user): bool
    {
        return $this->owner === $user || $this->collaborators->contains($user);
    }

    public function logCollaboratorAdded(User $user, User $collaborator): static
    {
        return $this->addToHistory(
            'collaborator_added',
            $user,
            sprintf('Collaborateur ajouté : %s', $collaborator->getEmail())
        );
    }

    /**
     * Auto-association depuis l'espace « Projets disponibles » (MeController::joinProject) —
     * entrée dédiée pour que l'admin distingue, dans l'historique, une association
     * spontanée d'un freelance d'un ajout qu'il a lui-même effectué (logCollaboratorAdded).
     */
    public function logCollaboratorSelfJoined(User $collaborator): static
    {
        return $this->addToHistory(
            'collaborator_self_joined',
            $collaborator,
            sprintf('%s s\'est auto-associé à ce projet depuis l\'espace freelance', $collaborator->getEmail())
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
        // bccomp() renvoie -1/0/1 (comparaison), pas un ratio : l'ancienne version
        // affichait donc -100/0/100 au lieu d'un vrai pourcentage. bcdiv/bcmul
        // calculent le vrai ratio dépensé/budget, cohérent avec le stockage
        // bcmath (chaînes décimales) du reste de l'entité.
        if (0 === bccomp($this->budget, '0.00', 2)) {
            return 0.0;
        }

        return min(100.0, (float) bcmul(bcdiv($this->spent, $this->budget, 6), '100', 2));
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