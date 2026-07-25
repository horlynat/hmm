<?php

namespace App\Entity;

use App\Repository\TimeEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Saisie de temps passé sur un projet (et éventuellement une tâche précise).
 * Base du suivi « régie / temps passé » et de la charge réelle.
 */
#[ORM\Entity(repositoryClass: TimeEntryRepository::class)]
#[ORM\Table(name: 'project_time_entry')]
#[ORM\Index(columns: ['project_id'], name: 'idx_time_entry_project')]
#[ORM\Index(columns: ['spent_on'], name: 'idx_time_entry_spent_on')]
class TimeEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_admin'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'timeEntries')]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\ManyToOne(targetEntity: ProjectTask::class)]
    #[ORM\JoinColumn(name: 'task_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['api_admin'])]
    private ?ProjectTask $task = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    #[Groups(['api_admin'])]
    private User $user;

    /** Durée en minutes (stockage entier, évite les flottants). */
    #[ORM\Column(type: 'integer')]
    #[Assert\Positive(message: 'La durée doit être strictement positive.')]
    #[Groups(['api_admin'])]
    private int $minutes = 0;

    #[ORM\Column(name: 'spent_on', type: 'date_immutable')]
    #[Groups(['api_admin'])]
    private \DateTimeImmutable $spentOn;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000)]
    #[Groups(['api_admin'])]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->spentOn = new \DateTimeImmutable('today');
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

    public function getTask(): ?ProjectTask
    {
        return $this->task;
    }

    public function setTask(?ProjectTask $task): static
    {
        $this->task = $task;

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

    public function getMinutes(): int
    {
        return $this->minutes;
    }

    public function setMinutes(int $minutes): static
    {
        $this->minutes = $minutes;

        return $this;
    }

    public function getSpentOn(): \DateTimeImmutable
    {
        return $this->spentOn;
    }

    public function setSpentOn(\DateTimeImmutable $spentOn): static
    {
        $this->spentOn = $spentOn;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Durée formatée « 2 h 30 » / « 45 min ». */
    public function getFormattedDuration(): string
    {
        $h = intdiv($this->minutes, 60);
        $m = $this->minutes % 60;

        if (0 === $h) {
            return $m.' min';
        }

        return 0 === $m ? $h.' h' : sprintf('%d h %02d', $h, $m);
    }
}
