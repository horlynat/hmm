<?php

namespace App\Entity;

use App\Enum\TaskStatusEnum;
use App\Repository\ProjectTaskRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Tâche / jalon d'un projet — suivi « étape par étape ». L'avancement du projet
 * (`Project::progress`) est dérivé du ratio de tâches terminées (cf. Project::recalculateProgress()).
 */
#[ORM\Entity(repositoryClass: ProjectTaskRepository::class)]
#[ORM\Table(name: 'project_task')]
#[ORM\Index(columns: ['project_id'], name: 'idx_project_task_project')]
class ProjectTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_admin'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre de la tâche est obligatoire.")]
    #[Assert\Length(max: 255)]
    #[Groups(['api_admin'])]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['api_admin'])]
    private ?string $description = null;

    #[ORM\Column(type: 'string', enumType: TaskStatusEnum::class, options: ['default' => 'todo'])]
    #[Groups(['api_admin'])]
    private TaskStatusEnum $status = TaskStatusEnum::TODO;

    /** Responsable de la tâche — doit faire partie de l'équipe du projet (owner/collaborateur). */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'assignee_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['api_admin'])]
    private ?User $assignee = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['api_admin'])]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['api_admin'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['api_admin'])]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
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

    public function getStatus(): TaskStatusEnum
    {
        return $this->status;
    }

    public function setStatus(TaskStatusEnum $status): static
    {
        $this->status = $status;
        // Horodatage de complétion cohérent avec le statut.
        if (TaskStatusEnum::DONE === $status && null === $this->completedAt) {
            $this->completedAt = new \DateTimeImmutable();
        } elseif (TaskStatusEnum::DONE !== $status) {
            $this->completedAt = null;
        }
        return $this;
    }

    public function getAssignee(): ?User
    {
        return $this->assignee;
    }

    public function setAssignee(?User $assignee): static
    {
        $this->assignee = $assignee;
        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    // ========== Business ==========

    public function isDone(): bool
    {
        return $this->status->isDone();
    }

    /** Tâche en retard : échéance dépassée et non terminée. */
    public function isOverdue(): bool
    {
        return null !== $this->dueDate
            && !$this->isDone()
            && $this->dueDate < new \DateTimeImmutable('today');
    }
}
