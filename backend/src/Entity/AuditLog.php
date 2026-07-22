<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Index(columns: ['created_at'], name: 'idx_audit_log_created_at')]
#[ORM\HasLifecycleCallbacks]
class AuditLog
{
    use CreatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_admin'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['api_admin'])]
    private string $entityClass;

    #[ORM\Column]
    #[Groups(['api_admin'])]
    private int $entityId;

    #[ORM\Column(length: 255)]
    #[Groups(['api_admin'])]
    private string $entityLabel;

    #[ORM\Column(length: 30)]
    #[Groups(['api_admin'])]
    private string $action;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['api_admin'])]
    private ?User $user = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['api_admin'])]
    private ?string $details = null;

    public function __construct(
        string $entityClass,
        int $entityId,
        string $entityLabel,
        string $action,
        ?User $user = null,
        ?string $details = null,
    ) {
        $this->entityClass = $entityClass;
        $this->entityId = $entityId;
        $this->entityLabel = $entityLabel;
        $this->action = $action;
        $this->user = $user;
        $this->details = $details;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function getEntityLabel(): string
    {
        return $this->entityLabel;
    }

    public function getEntityShortName(): string
    {
        $parts = explode('\\', $this->entityClass);

        return end($parts);
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }
}
