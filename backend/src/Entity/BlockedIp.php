<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Repository\BlockedIpRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * IP bloquée en écriture pour toute tentative de connexion (formulaire web
 * ET API JWT — cf. IpBlockSubscriber, seul point d'application). Distinct du
 * rate-limiter Symfony existant (5 tentatives/minute/IP, temporaire et
 * automatique) : ceci est une décision manuelle d'un admin, persistante
 * jusqu'à expiration explicite ou déblocage.
 */
#[ORM\Entity(repositoryClass: BlockedIpRepository::class)]
#[ORM\Table(name: 'blocked_ip')]
#[ORM\UniqueConstraint(name: 'uniq_blocked_ip_address', columns: ['ip'])]
#[ORM\HasLifecycleCallbacks]
class BlockedIp
{
    use CreatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_admin'])]
    private ?int $id = null;

    #[ORM\Column(length: 45)]
    #[Groups(['api_admin'])]
    private string $ip;

    #[ORM\Column(length: 255)]
    #[Groups(['api_admin'])]
    private string $reason;

    /** Email de l'admin qui a posé le blocage — texte libre plutôt qu'une relation
     *  User : reste lisible même si le compte de l'admin est supprimé plus tard. */
    #[ORM\Column(length: 180, nullable: true)]
    #[Groups(['api_admin'])]
    private ?string $blockedByLabel = null;

    /** null = blocage permanent, jusqu'à déblocage manuel. */
    #[ORM\Column(nullable: true)]
    #[Groups(['api_admin'])]
    private ?\DateTimeImmutable $expiresAt = null;

    public function __construct(string $ip, string $reason, ?string $blockedByLabel = null, ?\DateTimeImmutable $expiresAt = null)
    {
        $this->ip = $ip;
        $this->reason = $reason;
        $this->blockedByLabel = $blockedByLabel;
        $this->expiresAt = $expiresAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getBlockedByLabel(): ?string
    {
        return $this->blockedByLabel;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return null !== $this->expiresAt && $this->expiresAt < new \DateTimeImmutable();
    }
}
