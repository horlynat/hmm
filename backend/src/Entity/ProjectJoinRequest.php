<?php

namespace App\Entity;

use App\Enum\ProjectJoinRequestStatusEnum;
use App\Repository\ProjectJoinRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Demande d'auto-association d'un freelance à un projet "à venir", depuis
 * l'espace « Projets disponibles » (App\Controller\Api\MeController::joinProject).
 *
 * Contrairement à la version initiale de cette fonctionnalité, une demande
 * "en attente" ne donne AUCUN accès au projet — le freelance n'est ajouté à
 * Project::$collaborators (et ne peut donc "faire quoi que ce soit" dessus)
 * qu'après validation explicite d'un admin
 * (App\Controller\Admin\AdminProjectController::approveJoinRequest), qui
 * notifie alors le client du démarrage du développement
 * (App\Service\ProjectNotifier::developmentStarted).
 */
#[ORM\Entity(repositoryClass: ProjectJoinRequestRepository::class)]
#[ORM\Table(name: 'project_join_request')]
#[ORM\Index(columns: ['project_id', 'status'], name: 'idx_join_request_project_status')]
#[ORM\UniqueConstraint(name: 'uniq_active_join_request', columns: ['project_id', 'user_id', 'status'])]
class ProjectJoinRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['api_admin'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'joinRequests')]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['api_admin'])]
    private Project $project;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['api_admin'])]
    private User $user;

    #[ORM\Column(type: 'string', length: 20, enumType: ProjectJoinRequestStatusEnum::class)]
    #[Groups(['api_admin'])]
    private ProjectJoinRequestStatusEnum $status;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['api_admin'])]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['api_admin'])]
    private ?\DateTimeImmutable $decidedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'decided_by_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['api_admin'])]
    private ?User $decidedBy = null;

    public function __construct()
    {
        $this->status = ProjectJoinRequestStatusEnum::PENDING;
        $this->requestedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function setProject(Project $project): self
    {
        $this->project = $project;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getStatus(): ProjectJoinRequestStatusEnum
    {
        return $this->status;
    }

    public function approve(User $decidedBy): self
    {
        $this->status = ProjectJoinRequestStatusEnum::APPROVED;
        $this->decidedAt = new \DateTimeImmutable();
        $this->decidedBy = $decidedBy;
        return $this;
    }

    public function reject(User $decidedBy): self
    {
        $this->status = ProjectJoinRequestStatusEnum::REJECTED;
        $this->decidedAt = new \DateTimeImmutable();
        $this->decidedBy = $decidedBy;
        return $this;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getDecidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function getDecidedBy(): ?User
    {
        return $this->decidedBy;
    }
}
