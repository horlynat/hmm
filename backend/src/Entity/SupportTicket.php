<?php

namespace App\Entity;

use App\Enum\SupportTicketStatusEnum;
use App\Repository\SupportTicketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ticket de support client. Le premier message (celui de la création) est une
 * ligne SupportTicketMessage comme les autres — pas un champ $message séparé
 * ici — pour garder toute la correspondance au même endroit.
 *
 * $accessToken n'a volontairement aucun #[Groups] : il ne doit jamais être
 * sérialisé, y compris par l'API admin (api_admin) — le seul canal de
 * transmission au visiteur est l'email de confirmation/réponse.
 */
#[ORM\Entity(repositoryClass: SupportTicketRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_support_ticket_access_token', columns: ['access_token'])]
class SupportTicket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_admin'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    #[Assert\NotBlank(message: "Le nom est obligatoire.")]
    #[Assert\Length(max: 255)]
    private string $name = '';

    #[ORM\Column(length: 150)]
    #[Groups(['api_public', 'api_admin'])]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email]
    #[Assert\Length(max: 150)]
    private string $email = '';

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    #[Assert\NotBlank(message: "Le sujet est obligatoire.")]
    #[Assert\Length(max: 255)]
    private string $subject = '';

    #[ORM\Column(length: 20, enumType: SupportTicketStatusEnum::class)]
    #[Groups(['api_admin'])]
    private SupportTicketStatusEnum $status = SupportTicketStatusEnum::OPEN;

    #[ORM\Column(length: 64, unique: true)]
    private string $accessToken = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['api_admin'])]
    private ?User $user = null;

    #[ORM\Column]
    #[Groups(['api_admin'])]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, SupportTicketMessage> */
    #[ORM\OneToMany(mappedBy: 'ticket', targetEntity: SupportTicketMessage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getStatus(): SupportTicketStatusEnum
    {
        return $this->status;
    }

    public function setStatus(SupportTicketStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, SupportTicketMessage> */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(SupportTicketMessage $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setTicket($this);
        }

        return $this;
    }

    /**
     * Applique la règle de réouverture : une réponse invitée sur un ticket
     * résolu relance la conversation, une réponse invitée sur un ticket
     * ouvert/en cours ne change pas le statut (le client complète juste sa
     * demande avant la première réponse admin).
     */
    public function reopenIfResolved(): static
    {
        if (SupportTicketStatusEnum::RESOLVED === $this->status) {
            $this->status = SupportTicketStatusEnum::IN_PROGRESS;
        }

        return $this;
    }
}
