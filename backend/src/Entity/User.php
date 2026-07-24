<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface as TotpTwoFactorInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: "Il existe déjà un compte avec cet email.")]
#[ORM\HasLifecycleCallbacks] // ✅ Active les callbacks Doctrine
class User implements UserInterface, PasswordAuthenticatedUserInterface, TotpTwoFactorInterface
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
    private string $email = '';

    /** @var array<int, string> */
    #[ORM\Column]
    #[Groups(["api_admin"])]
    private array $roles = [];

    // Stocke le hash, jamais le mot de passe en clair : la complexité (longueur,
    // regex) se valide sur le champ de saisie non mappé "plainPassword" de chaque
    // formulaire (RegistrationFormType, ProfileType, UserType), pas ici — un hash
    // bcrypt/argon2 n'a aucune raison de satisfaire "au moins une majuscule".
    #[ORM\Column]
    private string $password = '';

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

    // ✅ Secret TOTP (base32) — non exposé via l'API, rempli seulement une fois
    // le code confirmé par l'utilisateur (voir TwoFactorController::setup).
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $totpSecret = null;

    /**
     * Codes de récupération 2FA — hachés (SHA-256), jamais stockés en clair.
     * Permettent de se connecter si l'appareil TOTP est perdu ; chaque code est
     * à usage unique (retiré de la liste après emploi). Jamais exposé via l'API.
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $backupCodes = null;

    // ✅ Typage corrigé : Collection au lieu de ArrayCollection
    /** @var Collection<int, LoginHistory> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: LoginHistory::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $loginHistory;

    /** @var Collection<int, Experience> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Experience::class)]
    #[Groups(["api_user"])]
    private Collection $experience;

    /** @var Collection<int, Course> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Course::class)]
    #[Groups(["api_user"])]
    private Collection $course;

    /** @var Collection<int, QuoteRequest> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: QuoteRequest::class)]
    private Collection $quoteRequest;

    /** @var Collection<int, Project> */
    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Project::class)]
    private Collection $ownedProjects;

    /** @var Collection<int, Project> */
    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'collaborators')]
    private Collection $collaboratingProjects;

    public function __construct()
    {
        $this->loginHistory = new ArrayCollection();
        $this->experience = new ArrayCollection();
        $this->course = new ArrayCollection();
        $this->quoteRequest = new ArrayCollection();
        // $this->createdAt = new \DateTimeImmutable(); // ✅ Utilisation de \DateTimeImmutable
        // $this->updatedAt = new \DateTimeImmutable(); // ✅ Utilisation de \DateTimeImmutable
        $this->ownedProjects = new ArrayCollection();
        $this->collaboratingProjects = new ArrayCollection();
    }

    // ===== Getters et Setters =====
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
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
        return $this->email;
    }

    /** @return array<int, string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    /** @param array<int, string> $roles */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string
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

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): self
    {
        $this->totpSecret = $totpSecret;
        return $this;
    }

    /**
     * @return list<string> Hachés (SHA-256), jamais en clair.
     */
    public function getBackupCodes(): array
    {
        return $this->backupCodes ?? [];
    }

    /**
     * @param list<string> $backupCodes Doivent déjà être hachés par l'appelant (BackupCodeManager).
     */
    public function setBackupCodes(array $backupCodes): self
    {
        $this->backupCodes = [] === $backupCodes ? null : $backupCodes;
        return $this;
    }

    // ===== Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface =====

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->isTwoFactorEnabled && null !== $this->totpSecret;
    }

    public function getTotpAuthenticationUsername(): ?string
    {
        return $this->email;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if (null === $this->totpSecret) {
            return null;
        }

        return new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    /**
     * @return Collection<int, LoginHistory>
     */
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
        $this->loginHistory->removeElement($loginHistory);
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
        $this->experience->removeElement($experience);
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
        $this->course->removeElement($course);
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
        $this->quoteRequest->removeElement($quoteRequest);
        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getOwnedProjects(): Collection
    {
        return $this->ownedProjects;
    }

    public function addOwnedProject(Project $project): self
    {
        if (!$this->ownedProjects->contains($project)) {
            $this->ownedProjects->add($project);
            $project->setOwner($this);
        }
        return $this;
    }

    public function removeOwnedProject(Project $project): self
    {
        $this->ownedProjects->removeElement($project);
        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getCollaboratingProjects(): Collection
    {
        return $this->collaboratingProjects;
    }

    public function addCollaboratingProject(Project $project): self
    {
        if (!$this->collaboratingProjects->contains($project)) {
            $this->collaboratingProjects->add($project);
            $project->addCollaborator($this);

            // Un pro/freelance associé à un projet devient automatiquement collaborateur,
            // sauf s'il détient déjà un rôle d'administration.
            if (!in_array('ROLE_ADMIN', $this->roles, true) && !in_array('ROLE_EDITOR', $this->roles, true)) {
                $this->roles[] = 'ROLE_EDITOR';
            }
        }
        return $this;
    }

    public function removeCollaboratingProject(Project $project): self
    {
        if ($this->collaboratingProjects->removeElement($project)) {
            $project->removeCollaborator($this);

            // Retire le rôle collaborateur si l'utilisateur ne participe plus à aucun projet.
            if ($this->collaboratingProjects->isEmpty()) {
                $this->roles = array_values(array_diff($this->roles, ['ROLE_EDITOR']));
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
