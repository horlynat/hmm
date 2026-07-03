<?php

namespace App\Entity;

use App\Repository\ExperienceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ExperienceRepository::class)]
class Experience
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le nom de l'entreprise est obligatoire.")]
    #[Assert\Length(
        min: 2,
        minMessage: "Le nom de l'entreprise doit contenir au moins {{ limit }} caractères.",
        max: 100,
        maxMessage: "Le nom de l'entreprise ne peut pas dépasser {{ limit }} caractères."
    )]
    private ?string $company = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Le role ne peut pas dépasser {{ limit }} caractères"
    )]
    private ?string $role = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de début est obligatoire.")]
    #[Assert\Type(\DateTimeImmutable::class)]
    #[Assert\LessThanOrEqual("today", message: "La date de début ne peut pas être dans le futur.")]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Type(\DateTimeImmutable::class)]
    #[Assert\Expression(
        "this.getEndDate() === null or this.getEndDate() >= this.getStartDate()",
        message: "La date de fin doit être postérieure ou égale à la date de début."
    )]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "La description est obligatoire.")]
    #[Assert\Length(
        min: 10,
        minMessage: "La description doit contenir au moins {{ limit }} caractères."
    )]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'experience')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Un utilisateur doit être associé à l'expérience.")]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(string $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

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
