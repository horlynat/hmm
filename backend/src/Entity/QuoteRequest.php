<?php

namespace App\Entity;

use App\Repository\QuoteRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuoteRequestRepository::class)]
class QuoteRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_admin'])] // exposé uniquement côté admin
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom est obligatoire.")]
    #[Assert\Length(min: 2, max: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $name = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email]
    #[Assert\Length(max: 100)]
    #[Groups(['api_admin'])] // email visible côté admin uniquement
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\NotBlank(message: "Le numéro de téléphone est obligatoire.")]
    #[Assert\Regex(pattern: "/^\+?[0-9\s\-]{7,20}$/")]
    #[Groups(['api_admin'])] // téléphone visible côté admin uniquement
    private ?string $phone = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Le message est obligatoire.")]
    #[Assert\Length(min: 10)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $message = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['api_admin'])]
    private ?bool $status = null;

    #[ORM\ManyToOne(inversedBy: 'quoteRequest')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Un utilisateur doit être associé à la demande.")]
    #[Groups(['api_admin'])]
    private ?User $user = null;
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): ?string
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

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }
    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function isStatus(): ?bool
    {
        return $this->status;
    }

    public function setStatus(?bool $status): static
    {
        $this->status = $status;

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
}
