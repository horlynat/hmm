<?php

namespace App\Entity;

use App\Enum\CourseTypeEnum;
use App\Repository\CourseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_public', 'api_admin'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire")]
    #[Assert\Length(max: 255, maxMessage: "Le titre ne peut pas dépasser {{ limit }} caractères")]
    #[Groups(['api_public', 'api_admin'])]
    private string $title = '';

    /** Titre en anglais — optionnel, retombe sur `title` (FR) côté frontend si vide. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $titleEn = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "L'institution est obligatoire")]
    #[Assert\Length(max: 100, maxMessage: "Le nom de l'institution ne peut pas dépasser {{ limit }} caractères")]
    #[Groups(['api_public', 'api_admin'])]
    private string $institution = '';

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de début est obligatoire")]
    #[Assert\Type(\DateTimeImmutable::class)]
    #[Groups(['api_public', 'api_admin'])]
    private \DateTimeImmutable $startDate;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de fin est obligatoire")]
    #[Assert\Type(\DateTimeImmutable::class)]
    #[Assert\GreaterThan(propertyPath: "startDate", message: "La date de fin doit être postérieure à la date de début")]
    #[Groups(['api_public', 'api_admin'])]
    private \DateTimeImmutable $endDate;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "La description est obligatoire")]
    #[Assert\Length(min: 10, minMessage: "La description doit contenir au moins {{ limit }} caractères")]
    #[Groups(['api_public', 'api_admin'])]
    private string $description = '';

    /** Description en anglais — optionnel, retombe sur `description` (FR) côté frontend si vide. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $descriptionEn = null;

    #[ORM\ManyToOne(inversedBy: 'course')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Le cours doit être lié à un utilisateur")]
    #[Groups(['api_admin'])] // exposé seulement côté admin
    private User $user;

    /** Permet au frontend de regrouper diplômes/certifications/formations plutôt que tout lister à plat. */
    #[ORM\Column(length: 20, enumType: CourseTypeEnum::class)]
    #[Assert\NotNull(message: "Le type de formation est obligatoire")]
    #[Groups(['api_public', 'api_admin'])]
    private CourseTypeEnum $type = CourseTypeEnum::DIPLOMA;

    public function __construct()
    {
        $this->startDate = new \DateTimeImmutable();
        $this->endDate = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getTitleEn(): ?string
    {
        return $this->titleEn;
    }

    public function setTitleEn(?string $titleEn): static
    {
        $this->titleEn = $titleEn;

        return $this;
    }

    public function getInstitution(): string
    {
        return $this->institution;
    }

    public function setInstitution(string $institution): static
    {
        $this->institution = $institution;

        return $this;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescriptionEn(): ?string
    {
        return $this->descriptionEn;
    }

    public function setDescriptionEn(?string $descriptionEn): static
    {
        $this->descriptionEn = $descriptionEn;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getType(): CourseTypeEnum
    {
        return $this->type;
    }

    public function setType(CourseTypeEnum $type): static
    {
        $this->type = $type;

        return $this;
    }
}
