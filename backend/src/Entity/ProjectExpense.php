<?php

namespace App\Entity;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ExpenseCategoryEnum;
use App\Enum\ExpenseStatusEnum;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'project_expenses')]
#[ORM\Index(columns: ['project_id'], name: 'idx_project_expense_project')]
#[ORM\Index(columns: ['created_at'], name: 'idx_project_expense_created_at')]
class ProjectExpense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_admin'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'expenses')]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['api_admin'])]
    private Project $project;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\NotBlank(message: "Le montant est obligatoire.")]
    #[Assert\Positive(message: "Le montant doit être strictement positif.")]
    #[Groups(['api_admin'])]
    private string $amount = '0.00';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: "La description ne peut pas dépasser {{ limit }} caractères.")]
    #[Groups(['api_admin'])]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    #[Groups(['api_admin'])]
    private User $user;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['api_admin'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'string', enumType: ExpenseCategoryEnum::class, options: ['default' => 'other'])]
    #[Groups(['api_admin'])]
    private ExpenseCategoryEnum $category = ExpenseCategoryEnum::OTHER;

    /** Date effective de la dépense (peut différer de la date de saisie). */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['api_admin'])]
    private ?\DateTimeImmutable $spentAt = null;

    #[ORM\Column(type: 'string', enumType: ExpenseStatusEnum::class, options: ['default' => 'pending'])]
    #[Groups(['api_admin'])]
    private ExpenseStatusEnum $status = ExpenseStatusEnum::PENDING;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'approved_by_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['api_admin'])]
    private ?User $approvedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['api_admin'])]
    private ?\DateTimeImmutable $approvedAt = null;

    /** Chemin du justificatif uploadé (reçu, facture) — via MediaUploader. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_admin'])]
    private ?string $receiptPath = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ========== Getters et Setters ==========

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

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
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

    public function getCategory(): ExpenseCategoryEnum
    {
        return $this->category;
    }

    public function setCategory(ExpenseCategoryEnum $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getSpentAt(): ?\DateTimeImmutable
    {
        return $this->spentAt;
    }

    public function setSpentAt(?\DateTimeImmutable $spentAt): static
    {
        $this->spentAt = $spentAt;
        return $this;
    }

    public function getStatus(): ExpenseStatusEnum
    {
        return $this->status;
    }

    public function setStatus(ExpenseStatusEnum $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?User $approvedBy): static
    {
        $this->approvedBy = $approvedBy;
        return $this;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): static
    {
        $this->approvedAt = $approvedAt;
        return $this;
    }

    public function getReceiptPath(): ?string
    {
        return $this->receiptPath;
    }

    public function setReceiptPath(?string $receiptPath): static
    {
        $this->receiptPath = $receiptPath;
        return $this;
    }

    // ========== Méthodes Utilitaires & Business Logique ==========

    public function isApproved(): bool
    {
        return ExpenseStatusEnum::APPROVED === $this->status;
    }

    public function isPending(): bool
    {
        return ExpenseStatusEnum::PENDING === $this->status;
    }

    public function isRejected(): bool
    {
        return ExpenseStatusEnum::REJECTED === $this->status;
    }

    /** Date effective si renseignée, sinon la date de saisie. */
    public function getEffectiveDate(): \DateTimeImmutable
    {
        return $this->spentAt ?? $this->createdAt;
    }

    /**
     * Retourne le montant proprement formaté avec le symbole monétaire.
     */
    public function getFormattedAmount(): string
    {
        return number_format((float) $this->amount, 2, ',', ' ') . ' €';
    }
}