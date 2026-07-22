<?php

namespace App\Entity;

use App\Enum\ContactMessageStatusEnum;
use App\Repository\ContactMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContactMessageRepository::class)]
class ContactMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["api_admin"])] // exposé uniquement côté admin
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["api_public", "api_admin"])]
    #[Assert\NotBlank(message: "Le nom est obligatoire")]
    #[Assert\Length(max: 255)]
    private string $name = '';

    #[ORM\Column(length: 150)]
    #[Groups(["api_admin"])] // email visible uniquement côté admin
    #[Assert\NotBlank(message: "L'email est obligatoire")]
    #[Assert\Email]
    #[Assert\Length(max: 150)]
    private string $email = '';

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
