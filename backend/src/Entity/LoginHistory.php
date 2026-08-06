<?php

// src/Entity/LoginHistory.php

namespace App\Entity;

use App\Repository\LoginHistoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: LoginHistoryRepository::class)]
class LoginHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["api_admin"])] // exposé uniquement côté admin
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'loginHistory')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(["api_admin"])] // visible uniquement côté admin
    private User $user;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(["api_admin"])]
    private \DateTimeImmutable $loginAt;

    #[ORM\Column(length: 45, nullable: true)]
    #[Groups(["api_admin"])]
    private ?string $ip = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(["api_admin"])]
    private ?string $device = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(["api_admin"])]
    private ?string $location = null;

    public function __construct()
    {
        $this->loginAt = new \DateTimeImmutable();
    }
    public function getId(): ?int
    {
        return $this->id;
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

    public function getLoginAt(): \DateTimeImmutable
    {
        return $this->loginAt;
    }

    public function setLoginAt(\DateTimeImmutable $loginAt): self
    {
        $this->loginAt = $loginAt;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getDevice(): ?string
    {
        return $this->device;
    }

    public function setDevice(?string $device): self
    {
        $this->device = $device;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;

        return $this;
    }
}
