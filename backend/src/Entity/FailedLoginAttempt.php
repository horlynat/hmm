<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Repository\FailedLoginAttemptRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: FailedLoginAttemptRepository::class)]
#[ORM\Index(columns: ['ip'], name: 'idx_failed_login_ip')]
#[ORM\Index(columns: ['created_at'], name: 'idx_failed_login_created_at')]
#[ORM\HasLifecycleCallbacks]
class FailedLoginAttempt
{
    use CreatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_admin'])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Groups(['api_admin'])]
    private string $email;

    #[ORM\Column(length: 45, nullable: true)]
    #[Groups(['api_admin'])]
    private ?string $ip = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_admin'])]
    private ?string $userAgent = null;

    #[ORM\Column(length: 30)]
    #[Groups(['api_admin'])]
    private string $reason;

    public function __construct(string $email, string $reason, ?string $ip = null, ?string $userAgent = null)
    {
        $this->email = $email;
        $this->reason = $reason;
        $this->ip = $ip;
        $this->userAgent = $userAgent;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getReasonLabel(): string
    {
        return match ($this->reason) {
            'bad_credentials' => 'Mot de passe incorrect',
            'unverified_account' => 'Compte non vérifié',
            'inactive_account' => 'Compte désactivé',
            'rate_limited' => 'Trop de tentatives (rate-limit)',
            'unknown_user' => 'Utilisateur introuvable',
            default => ucfirst($this->reason),
        };
    }
}
