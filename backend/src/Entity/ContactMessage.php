<?php

namespace App\Entity;

use App\Enum\ContactMessageStatusEnum;
use App\Repository\ContactMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ContactMessageRepository::class)]
class ContactMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["api_admin"])] // exposé uniquement côté admin
    private ?int $id = null;

    /** Origine du message : "Rendez-vous", "Candidature freelance"... — permet de distinguer les flux publics qui partagent tous cette même entité générique. */
    #[ORM\Column(length: 100)]
    #[Groups(["api_public", "api_admin"])]
    #[Assert\NotBlank(message: "L'origine du message est obligatoire")]
    private string $source = '';

    #[ORM\Column(length: 255)]
    #[Groups(["api_public", "api_admin"])]
    #[Assert\NotBlank(message: "Le nom est obligatoire")]
    #[Assert\Length(max: 255)]
    private string $name = '';

    /** Renseignée si le contact écrit au nom d'une société plutôt qu'à titre individuel. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(["api_public", "api_admin"])]
    private ?string $company = null;

    #[ORM\Column(length: 150)]
    #[Groups(["api_public", "api_admin"])]
    #[Assert\NotBlank(message: "L'email est obligatoire")]
    #[Assert\Email]
    #[Assert\Length(max: 150)]
    private string $email = '';

    /** Requis uniquement si `channel` n'est pas l'email — cf. validatePhoneForChannel(). */
    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(pattern: "/^\+?[0-9\s\-]{7,20}$/", message: "Le numéro de téléphone n'est pas valide.")]
    #[Groups(["api_public", "api_admin"])]
    private ?string $phone = null;

    /** Canal de contact préféré : Email, WhatsApp ou Appel. Non pertinent pour tous les flux (ex : candidature freelance), donc nullable. */
    #[ORM\Column(length: 30, nullable: true)]
    #[Groups(["api_public", "api_admin"])]
    private ?string $channel = null;

    /** Créneau souhaité pour un échange (flux "Rendez-vous" uniquement). */
    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(["api_public", "api_admin"])]
    private ?string $slot = null;

    #[ORM\Column(length: 255)]
    #[Groups(["api_public", "api_admin"])]
    #[Assert\NotBlank(message: "Le sujet est obligatoire")]
    #[Assert\Length(max: 255)]
    private string $subject = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(["api_public", "api_admin"])]
    #[Assert\NotBlank(message: "Le message est obligatoire")]
    #[Assert\Length(min: 10)]
    private string $message = '';

    #[ORM\Column]
    #[Groups(["api_admin"])]
    #[Assert\NotNull(message: "La date de création est obligatoire")]
    #[Assert\Type(\DateTimeImmutable::class)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 20, enumType: ContactMessageStatusEnum::class)]
    #[Groups(["api_admin"])]
    private ContactMessageStatusEnum $status = ContactMessageStatusEnum::NEW;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Le téléphone n'est pas obligatoire dans l'absolu (le champ reste nullable),
     * mais devient requis dès que le contact choisit WhatsApp ou un appel comme
     * canal préféré — impossible de le rappeler sinon. Cf. QuoteRequest, même règle.
     */
    #[Assert\Callback]
    public function validatePhoneForChannel(ExecutionContextInterface $context): void
    {
        if (null === $this->channel || '' === $this->channel) {
            return;
        }

        $channel = mb_strtolower($this->channel);
        $isEmailChannel = str_contains($channel, 'email') || str_contains($channel, 'mail');

        if (!$isEmailChannel && '' === trim((string) $this->phone)) {
            $context->buildViolation('Le téléphone est obligatoire pour le canal de contact choisi (WhatsApp ou appel).')
                ->atPath('phone')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
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

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): static
    {
        $this->company = $company;

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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function setChannel(?string $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function getSlot(): ?string
    {
        return $this->slot;
    }

    public function setSlot(?string $slot): static
    {
        $this->slot = $slot;

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

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getStatus(): ContactMessageStatusEnum
    {
        return $this->status;
    }

    public function setStatus(ContactMessageStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function markAsRead(): static
    {
        if (ContactMessageStatusEnum::NEW === $this->status) {
            $this->status = ContactMessageStatusEnum::READ;
        }

        return $this;
    }

    public function archive(): static
    {
        $this->status = ContactMessageStatusEnum::ARCHIVED;

        return $this;
    }
}
