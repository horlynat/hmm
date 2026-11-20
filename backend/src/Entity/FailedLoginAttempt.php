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
        // Tronqué aux longueurs de colonne (P1 #10) : l'e-mail tenté et le
        // User-Agent sont contrôlés par un anonyme ; un dépassement faisait
        // échouer le flush (MySQL strict) et donc disparaître l'échec du journal.
        $this->email = mb_substr($email, 0, 180);
        $this->reason = mb_substr($reason, 0, 30);
        $this->ip = null === $ip ? null : mb_substr($ip, 0, 45);
        $this->userAgent = null === $userAgent ? null : mb_substr($userAgent, 0, 255);
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
            'locked_account' => 'Compte verrouillé (échecs répétés)',
            'expired_account' => 'Compte expiré',
            'rate_limited' => 'Trop de tentatives (rate-limit)',
            'unknown_user' => 'Utilisateur introuvable',
            default => ucfirst($this->reason),
        };
    }
}
