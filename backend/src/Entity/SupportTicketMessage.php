<?php

namespace App\Entity;

use App\Repository\SupportTicketMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un message du fil d'un ticket support, côté admin ou côté client
 * ($fromAdmin) — un simple booléen suffit, pas besoin d'un enum pour "qui a
 * écrit ceci" qui n'a que deux valeurs possibles.
 */
#[ORM\Entity(repositoryClass: SupportTicketMessageRepository::class)]
class SupportTicketMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false)]
    private SupportTicket $ticket;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Le message est obligatoire.")]
    #[Assert\Length(min: 10, max: 5000)]
    private string $body = '';

    #[ORM\Column]
    private bool $fromAdmin = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicket(): SupportTicket
    {
        return $this->ticket;
    }

    public function setTicket(SupportTicket $ticket): static
    {
        $this->ticket = $ticket;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function isFromAdmin(): bool
    {
        return $this->fromAdmin;
    }

    public function setFromAdmin(bool $fromAdmin): static
    {
        $this->fromAdmin = $fromAdmin;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
