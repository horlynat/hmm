<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Repository\UserSessionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: UserSessionRepository::class)]
#[ORM\Index(columns: ['session_id'], name: 'idx_user_session_session_id')]
#[ORM\HasLifecycleCallbacks]
class UserSession
{
    use CreatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_admin'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['api_admin'])]
    private User $user;

    #[ORM\Column(length: 128)]
    #[Groups(['api_admin'])]
    private string $sessionId;

    #[ORM\Column(length: 45, nullable: true)]
    #[Groups(['api_admin'])]
    private ?string $ip = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_admin'])]
    private ?string $userAgent = null;

    public function __construct(User $user, string $sessionId, ?string $ip = null, ?string $userAgent = null)
    {
        $this->user = $user;
        $this->sessionId = $sessionId;
        $this->ip = $ip;
        $this->userAgent = $userAgent;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }
}
