<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: "Il existe déjà un compte avec cet email.")]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["api_user", "api_admin"])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(["api_user", "api_admin"])]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "Veuillez entrer un email valide.")]
    private ?string $email = null;

    #[ORM\Column]
    #[Groups(["api_admin"])]
    private array $roles = [];

    #[ORM\Column]
    #[Assert\Length(min: 8, minMessage: "Le mot de passe doit contenir au moins {{ limit }} caractères.")]
    #[Assert\Regex(
        pattern: "/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&]).+$/",
        message: "Le mot de passe doit contenir une majuscule, une minuscule, un chiffre et un caractère spécial."
    )]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(["api_user", "api_admin"])]
    #[Assert\Length(max: 255, maxMessage: "Le nom complet ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $fullName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(["api_user", "api_admin"])]
    #[Assert\Url(message: "L'image de profil doit être une URL valide.")]
    private ?string $profileImage = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups(["api_admin"])]
    private bool $isVerified = false;

    #[ORM\Column(type: 'boolean')]
    #[Groups(["api_admin"])]
    private bool $isActive = true;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $lastIp = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastLocation = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastDevice = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(["api_admin"])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(["api_admin"])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $passwordChangedAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(["api_user", "api_admin"])]
    #[Assert\Length(min: 7, max: 20, minMessage: "Le numéro doit contenir au moins {{ limit }} caractères.")]
    #[Assert\Regex(
        pattern: '/^[0-9+\-\s\(\)]+$/',
        message: "Le numéro de téléphone contient des caractères invalides."
    )]
    private ?string $phone = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups(["api_admin"])]
    private bool $isTwoFactorEnabled = false;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: LoginHistory::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $loginHistory;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Experience::class)]
    #[Groups(["api_user"])]
    private Collection $experience;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Course::class)]
    #[Groups(["api_user"])]
    private Collection $course;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: QuoteRequest::class)]
    private Collection $quoteRequest;

    public function __construct()
    {
        $this->loginHistory = new ArrayCollection();
        $this->experience = new ArrayCollection();
        $this->course = new ArrayCollection();
        $this->quoteRequest = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ===== Getters et Setters =====
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): self
    {
        $this->fullName = $fullName;
        return $this;
    }

    public function getProfileImage(): ?string
    {
        return $this->profileImage;
    }

    public function setProfileImage(?string $profileImage): self
    {
        $this->profileImage = $profileImage;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getLastIp(): ?string
    {
        return $this->lastIp;
    }

    public function setLastIp(?string $lastIp): self
    {
        $this->lastIp = $lastIp;
        return $this;
    }

    public function getLastLocation(): ?string
    {
        return $this->lastLocation;
    }

    public function setLastLocation(?string $lastLocation): self
    {
        $this->lastLocation = $lastLocation;
        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function getLastDevice(): ?string
    {
        return $this->lastDevice;
    }

    public function setLastDevice(?string $lastDevice): self
    {
        $this->lastDevice = $lastDevice;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getPasswordChangedAt(): ?\DateTimeImmutable
    {
        return $this->passwordChangedAt;
    }

    public function setPasswordChangedAt(?\DateTimeImmutable $passwordChangedAt): self
    {
        $this->passwordChangedAt = $passwordChangedAt;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function isTwoFactorEnabled(): bool
    {
        return $this->isTwoFactorEnabled;
    }

    public function setIsTwoFactorEnabled(bool $isTwoFactorEnabled): self
    {
        $this->isTwoFactorEnabled = $isTwoFactorEnabled;
        return $this;
    }

    public function getLoginHistory(): Collection
    {
        return $this->loginHistory;
    }

    public function addLoginHistory(LoginHistory $loginHistory): self
    {
        if (!$this->loginHistory->contains($loginHistory)) {
            $this->loginHistory->add($loginHistory);
            $loginHistory->setUser($this);
        }
        return $this;
    }

    public function removeLoginHistory(LoginHistory $loginHistory): self
    {
        if ($this->loginHistory->removeElement($loginHistory)) {
            if ($loginHistory->getUser() === $this) {
                $loginHistory->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Experience>
     */
    public function getExperience(): Collection
    {
        return $this->experience;
    }

    public function addExperience(Experience $experience): self
    {
        if (!$this->experience->contains($experience)) {
            $this->experience->add($experience);
            $experience->setUser($this);
        }
        return $this;
    }

    public function removeExperience(Experience $experience): self
    {
        if ($this->experience->removeElement($experience)) {
            if ($experience->getUser() === $this) {
                $experience->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Course>
     */
    public function getCourse(): Collection
    {
        return $this->course;
    }

    public function addCourse(Course $course): self
    {
        if (!$this->course->contains($course)) {
            $this->course->add($course);
            $course->setUser($this);
        }
        return $this;
    }

    public function removeCourse(Course $course): self
    {
        if ($this->course->removeElement($course)) {
            if ($course->getUser() === $this) {
                $course->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, QuoteRequest>
     */
    public function getQuoteRequest(): Collection
    {
        return $this->quoteRequest;
    }

    public function addQuoteRequest(QuoteRequest $quoteRequest): self
    {
        if (!$this->quoteRequest->contains($quoteRequest)) {
            $this->quoteRequest->add($quoteRequest);
            $quoteRequest->setUser($this);
        }
        return $this;
    }

    public function removeQuoteRequest(QuoteRequest $quoteRequest): self
    {
        if ($this->quoteRequest->removeElement($quoteRequest)) {
            if ($quoteRequest->getUser() === $this) {
                $quoteRequest->setUser(null);
            }
        }
        return $this;
    }

    public function getProfileCompletionPercentage(): int
    {
        $totalFields = 5;
        $filledFields = 0;

        if ($this->fullName) $filledFields++;
        if ($this->email) $filledFields++;
        if ($this->phone) $filledFields++;
        if ($this->profileImage) $filledFields++;
        if ($this->lastLoginAt) $filledFields++;

        return (int) (($filledFields / $totalFields) * 100);
    }

    public function eraseCredentials(): void
    {
        // Si vous stockez des données temporaires sensibles sur l'utilisateur, effacez-les ici
    }
}