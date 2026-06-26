<?php

namespace App\Entity;


use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: "L'email est obligatoire")]
    #[Assert\Email(message: "Format d'email invalide")]
    private ?string $email = null;

    // Ajoutez cette propriété
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profileImage = null;

    // Ajoutez cette propriété (si vous voulez stocker le nom complet)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fullName = null;

    #[ORM\Column(length: 255)]
    // #[Assert\NotBlank(message: "Le mot de passe est obligatoire")]
    #[Assert\Length(min: 8, minMessage: "Le mot de passe doit contenir au moins 8 caractères")]
    private ?string $password = null;

    #[ORM\Column]
    private array $roles = [];

    /**
     * @var Collection<int, Experience>
     */
    #[ORM\OneToMany(targetEntity: Experience::class, mappedBy: 'user', cascade: ['remove'])]
    private Collection $experience;

    /**
     * @var Collection<int, Course>
     */
    #[ORM\OneToMany(targetEntity: Course::class, mappedBy: 'user', cascade: ['remove'])]
    private Collection $course;

    /**
     * @var Collection<int, QuoteRequest>
     */
    #[ORM\OneToMany(targetEntity: QuoteRequest::class, mappedBy: 'user', cascade: ['remove'])]
    private Collection $quoteRequest;

    #[ORM\Column]
    private bool $isVerified = false;

    public function __construct()
    {
        $this->experience = new ArrayCollection();
        $this->course = new ArrayCollection();
        $this->quoteRequest = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    // Ajoutez ces méthodes "Getter"
    public function getProfileImage(): ?string
    {
        return $this->profileImage;
    }

    public function setProfileImage(?string $profileImage): static
    {
        $this->profileImage = $profileImage;
        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName ?? $this->email; // Retourne l'email si le nom est vide
    }

    public function setFullName(?string $fullName): static
    {
        $this->fullName = $fullName;
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    // 🔑 Nettoyage des données sensibles
    public function eraseCredentials(): void
    {
        // Exemple : $this->plainPassword = null;
    }

    /**
     * @return Collection<int, Experience>
     */
    public function getExperience(): Collection
    {
        return $this->experience;
    }

    public function addExperience(Experience $experience): static
    {
        if (!$this->experience->contains($experience)) {
            $this->experience->add($experience);
            $experience->setUser($this);
        }

        return $this;
    }

    public function removeExperience(Experience $experience): static
    {
        if ($this->experience->removeElement($experience)) {
            // set the owning side to null (unless already changed)
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

    public function addCourse(Course $course): static
    {
        if (!$this->course->contains($course)) {
            $this->course->add($course);
            $course->setUser($this);
        }

        return $this;
    }

    public function removeCourse(Course $course): static
    {
        if ($this->course->removeElement($course)) {
            // set the owning side to null (unless already changed)
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

    public function addQuoteRequest(QuoteRequest $quoteRequest): static
    {
        if (!$this->quoteRequest->contains($quoteRequest)) {
            $this->quoteRequest->add($quoteRequest);
            $quoteRequest->setUser($this);
        }

        return $this;
    }

    public function removeQuoteRequest(QuoteRequest $quoteRequest): static
    {
        if ($this->quoteRequest->removeElement($quoteRequest)) {
            // set the owning side to null (unless already changed)
            if ($quoteRequest->getUser() === $this) {
                $quoteRequest->setUser(null);
            }
        }

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }
}
