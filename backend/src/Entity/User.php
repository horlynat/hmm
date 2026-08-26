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
// entityClass explicite : sans lui, la validation échoue avec "Unable to find
// the object manager" dès qu'on valide une sous-classe non mappée Doctrine
// (ex: CollaboratorRegistrationApiResource extends User, pattern API Platform).
#[UniqueEntity(fields: ['email'], message: "Il existe déjà un compte avec cet email.", entityClass: User::class)]
#[ORM\HasLifecycleCallbacks] // ✅ Active les callbacks Doctrine
class User implements UserInterface, PasswordAuthenticatedUserInterface, TotpTwoFactorInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["api_user", "api_admin"])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(["api_user", "api_admin", "collaborator_signup", "client_signup"])]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "Veuillez entrer un email valide.")]
    // protected (pas private) : UniqueEntityValidator reflète l'objet réellement
    // validé (potentiellement une sous-classe ApiResource comme
    // CollaboratorRegistrationApiResource) — une propriété private du parent est
    // invisible à ReflectionObject sur une instance de sous-classe, alors qu'une
    // propriété protected reste visible. Sans ça : "email is not a property of
    // class ...ApiResource".
    protected string $email = '';

    /** @var array<int, string> */
    #[ORM\Column]
    #[Groups(["api_admin"])]
    private array $roles = [];

    /**
     * PAM (Privileged Access Management) : ROLE_SUPER_ADMIN reste "dormant"
     * même si présent dans `$roles` (l'entitlement — le compte a le droit d'y
     * accéder) tant que ce champ est null ou dépassé. getRoles() (source
     * unique consultée par Symfony Security) ne restitue le rôle que pendant
     * cette fenêtre — cf. PrivilegeElevationController pour l'activation en
     * libre-service, avec re-saisie du mot de passe et journal d'audit.
     * Objectif : jamais de Super Admin permanent actif par défaut, comme
     * recommandé pour toute gestion IAM sérieuse.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $superAdminElevatedUntil = null;

    // Stocke le hash, jamais le mot de passe en clair : la complexité (longueur,
    // regex) se valide sur le champ de saisie non mappé "plainPassword" de chaque
    // formulaire (RegistrationFormType, ProfileType, UserType), pas ici — un hash
    // bcrypt/argon2 n'a aucune raison de satisfaire "au moins une majuscule".
    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(["api_user", "api_admin", "collaborator_signup", "client_signup"])]
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

    /**
     * Verrouillage temporaire — distinct de isActive (désactivation
     * délibérée par un admin) : celui-ci peut être posé automatiquement
     * (cf. LoginListener::onLoginFailure(), trop d'échecs de connexion sur
     * un même email) ou manuellement. Expire de lui-même : nul besoin de le
     * déverrouiller explicitement, il suffit d'attendre l'échéance.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(["api_admin"])]
    private ?\DateTimeImmutable $lockedUntil = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(["api_admin"])]
    private ?string $lockedReason = null;

    /** Compte à durée limitée (ex. contractuel, stagiaire) — nul = jamais d'expiration. */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(["api_admin"])]
    private ?\DateTimeImmutable $accountExpiresAt = null;

    /**
     * Compte technique (intégration/automatisation), pas une personne — créé
     * exclusivement via App\Command\CreateServiceAccountCommand (jamais de
     * formulaire web). Porte le rôle ROLE_SERVICE, volontairement absent de
     * role_hierarchy (security.yaml) : aucun accès par défaut au-delà de ce
     * qui sera explicitement accordé, ressource par ressource. Exclu des
     * emails de bienvenue et des listes d'utilisateurs humains (cf.
     * AccountWelcomeNotifier, UserRepository::clientsQueryBuilder()).
     */
    #[ORM\Column(type: 'boolean')]
    #[Groups(["api_admin"])]
    private bool $isSystemAccount = false;

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

    /**
     * Horodatage de la dernière demande de réinitialisation de mot de passe
     * (SecurityController::forgotPasswordRequest). Sert de jeton d'usage
     * unique côté serveur pour le JWT envoyé par email : ce dernier embarque
     * la même valeur dans son payload, donc un lien devient invalide dès
     * qu'il a servi une fois (remis à null) ou qu'une demande plus récente a
     * été faite (valeur remplacée) — sans avoir à stocker/révoquer le JWT
     * lui-même, qui reste sans état.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $passwordResetRequestedAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(["api_user", "api_admin", "collaborator_signup", "client_signup"])]
    #[Assert\Length(min: 7, max: 20, minMessage: "Le numéro doit contenir au moins {{ limit }} caractères.")]
    #[Assert\Regex(
        pattern: '/^[0-9+\-\s\(\)]+$/',
        message: "Le numéro de téléphone contient des caractères invalides."
    )]
    private ?string $phone = null;

    /**
     * Champs propres au profil "collaborateur" (pro/freelance) — renseignés à
     * l'inscription via le formulaire public dédié, visibles/éditables ensuite
     * par l'utilisateur (profil) et l'admin (fiche collaborateur).
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(["api_user", "api_admin", "collaborator_signup"])]
    private ?array $specialties = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(["api_user", "api_admin", "collaborator_signup"])]
    private ?string $availability = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(["api_user", "api_admin", "collaborator_signup"])]
    #[Assert\Url(message: "Le lien portfolio doit être une URL valide.")]
    private ?string $portfolioUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(["api_user", "api_admin", "collaborator_signup"])]
    private ?string $bio = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Groups(["api_user", "api_admin"])]
    #[Assert\Range(min: 0, max: 60, notInRangeMessage: "Le nombre d'années d'expérience doit être compris entre {{ min }} et {{ max }}.")]
    private ?int $yearsOfExperience = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(["api_user", "api_admin"])]
    private ?string $city = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(["api_user", "api_admin"])]
    #[Assert\Url(message: "Le lien LinkedIn doit être une URL valide.")]
    private ?string $linkedinUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(["api_user", "api_admin"])]
    #[Assert\Url(message: "Le lien GitHub doit être une URL valide.")]
    private ?string $githubUrl = null;

    /** Langues parlées — liste fermée, cf. MeController::ALLOWED_LANGUAGES. */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(["api_user", "api_admin"])]
    private ?array $languages = null;

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

    /**
     * Rôles effectivement actifs — source unique consultée par Symfony
     * Security (Voters, RoleHierarchy, IS_GRANTED). ROLE_SUPER_ADMIN en est
     * retiré tant qu'il n'est pas activement élevé (cf. docblock de
     * $superAdminElevatedUntil) : un compte qui A le droit d'être Super Admin
     * (hasSuperAdminEntitlement()) ne l'EST pas tant qu'il ne l'a pas
     * explicitement activé pour cette session.
     *
     * @return array<int, string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        if (\in_array('ROLE_SUPER_ADMIN', $roles, true) && !$this->isSuperAdminElevated()) {
            $roles = array_values(array_diff($roles, ['ROLE_SUPER_ADMIN']));
        }
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    /** Le compte a-t-il le droit de devenir Super Admin (indépendamment de savoir s'il l'est actuellement) ? */
    public function hasSuperAdminEntitlement(): bool
    {
        return \in_array('ROLE_SUPER_ADMIN', $this->roles, true);
    }

    public function isSuperAdminElevated(): bool
    {
        return null !== $this->superAdminElevatedUntil && $this->superAdminElevatedUntil > new \DateTimeImmutable();
    }

    public function getSuperAdminElevatedUntil(): ?\DateTimeImmutable
    {
        return $this->superAdminElevatedUntil;
    }

    public function setSuperAdminElevatedUntil(?\DateTimeImmutable $superAdminElevatedUntil): self
    {
        $this->superAdminElevatedUntil = $superAdminElevatedUntil;

        return $this;
    }

    /**
     * Rôle le plus élevé, pour l'affichage (un seul badge au lieu de lister
     * toute la chaîne héritée). Ordre calqué sur role_hierarchy dans
     * config/packages/security.yaml — à garder synchronisé si celle-ci change.
     */
    /**
     * Rôle le plus élevé, pour l'affichage (un seul badge au lieu de lister
     * toute la chaîne héritée) et le routage administratif (AccountLinkResolver,
     * AdminSecurityRoleController). Se base sur l'entitlement brut ($this->roles),
     * PAS sur getRoles() : un Super Admin dormant (cf. isSuperAdminElevated())
     * doit rester classé/routé comme Super Admin dans l'administration — seules
     * les décisions d'autorisation Symfony Security passent par getRoles().
     */
    public function getPrimaryRole(): string
    {
        static $hierarchyLowToHigh = [
            'ROLE_USER',
            'ROLE_EDITOR',
            'ROLE_MODERATOR',
            'ROLE_MANAGER',
            'ROLE_ADMIN',
            'ROLE_SUPER_ADMIN',
        ];

        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        foreach (array_reverse($hierarchyLowToHigh) as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return 'ROLE_USER';
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
        // Invalide tout lien de réinitialisation en attente : quel que soit le
        // chemin par lequel le mot de passe change (profil, admin, commande de
        // secours, ou ce flow de réinitialisation lui-même), un lien envoyé
        // avant ce changement ne doit plus pouvoir servir.
        $this->passwordResetRequestedAt = null;
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

    public function getLockedUntil(): ?\DateTimeImmutable
    {
        return $this->lockedUntil;
    }

    public function setLockedUntil(?\DateTimeImmutable $lockedUntil): self
    {
        $this->lockedUntil = $lockedUntil;
        return $this;
    }

    public function getLockedReason(): ?string
    {
        return $this->lockedReason;
    }

    public function setLockedReason(?string $lockedReason): self
    {
        $this->lockedReason = $lockedReason;
        return $this;
    }

    public function isLocked(): bool
    {
        return null !== $this->lockedUntil && $this->lockedUntil > new \DateTimeImmutable();
    }

    public function getAccountExpiresAt(): ?\DateTimeImmutable
    {
        return $this->accountExpiresAt;
    }

    public function setAccountExpiresAt(?\DateTimeImmutable $accountExpiresAt): self
    {
        $this->accountExpiresAt = $accountExpiresAt;
        return $this;
    }

    public function isExpired(): bool
    {
        return null !== $this->accountExpiresAt && $this->accountExpiresAt < new \DateTimeImmutable();
    }

    public function isSystemAccount(): bool
    {
        return $this->isSystemAccount;
    }

    public function setIsSystemAccount(bool $isSystemAccount): self
    {
        $this->isSystemAccount = $isSystemAccount;
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

    public function getPasswordResetRequestedAt(): ?\DateTimeImmutable
    {
        return $this->passwordResetRequestedAt;
    }

    public function setPasswordResetRequestedAt(?\DateTimeImmutable $passwordResetRequestedAt): self
    {
        $this->passwordResetRequestedAt = $passwordResetRequestedAt;
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

    public function getSpecialties(): ?array
    {
        return $this->specialties;
    }

    public function setSpecialties(?array $specialties): self
    {
        $this->specialties = $specialties;
        return $this;
    }

    public function getAvailability(): ?string
    {
        return $this->availability;
    }

    public function setAvailability(?string $availability): self
    {
        $this->availability = $availability;
        return $this;
    }

    public function getPortfolioUrl(): ?string
    {
        return $this->portfolioUrl;
    }

    public function setPortfolioUrl(?string $portfolioUrl): self
    {
        $this->portfolioUrl = $portfolioUrl;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }

    public function getYearsOfExperience(): ?int
    {
        return $this->yearsOfExperience;
    }

    public function setYearsOfExperience(?int $yearsOfExperience): self
    {
        $this->yearsOfExperience = $yearsOfExperience;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;
        return $this;
    }

    public function getLinkedinUrl(): ?string
    {
        return $this->linkedinUrl;
    }

    public function setLinkedinUrl(?string $linkedinUrl): self
    {
        $this->linkedinUrl = $linkedinUrl;
        return $this;
    }

    public function getGithubUrl(): ?string
    {
        return $this->githubUrl;
    }

    public function setGithubUrl(?string $githubUrl): self
    {
        $this->githubUrl = $githubUrl;
        return $this;
    }

    /** @return list<string>|null */
    public function getLanguages(): ?array
    {
        return $this->languages;
    }

    /** @param list<string>|null $languages */
    public function setLanguages(?array $languages): self
    {
        $this->languages = $languages;
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

    /**
     * Champs qui composent la complétude du profil "freelance" — distinct de
     * getProfileCompletionPercentage() ci-dessus (calcul générique utilisé
     * par le back-office admin, ne pas fusionner). Volontairement limité aux
     * champs que l'utilisateur peut réellement renseigner lui-même via
     * PATCH /api/me (App\Controller\Api\MeController::EDITABLE_FIELDS) :
     * `phone` en est explicitement exclu (verrouillé en self-service, cf.
     * commentaire sur EDITABLE_FIELDS) et il n'existe aujourd'hui aucun
     * moyen self-service de renseigner `profileImage` — les inclure ici
     * rendrait la complétude à 100 % inatteignable pour un utilisateur qui
     * ne les a pas renseignés à l'inscription (où ils sont eux aussi
     * optionnels).
     *
     * @return list<string>
     */
    private function freelanceProfileFields(): array
    {
        return [
            'fullName' => $this->fullName,
            'bio' => $this->bio,
            'specialties' => !empty($this->specialties) ? '1' : null,
            'availability' => $this->availability,
            'portfolioUrl' => $this->portfolioUrl,
            'yearsOfExperience' => null !== $this->yearsOfExperience ? (string) $this->yearsOfExperience : null,
            'city' => $this->city,
            // Un seul lien pro suffit (LinkedIn OU GitHub) — tous les
            // freelances n'ont pas de GitHub public (ex: designers).
            'professionalLinks' => ($this->linkedinUrl || $this->githubUrl) ? '1' : null,
            'languages' => !empty($this->languages) ? '1' : null,
        ];
    }

    public function getFreelanceProfileCompletionPercentage(): int
    {
        $fields = $this->freelanceProfileFields();
        $filled = \count(array_filter($fields, static fn ($f) => null !== $f && '' !== $f));

        return (int) round(100 * $filled / \count($fields));
    }

    public function isFreelanceProfileComplete(): bool
    {
        return 100 === $this->getFreelanceProfileCompletionPercentage();
    }

    /**
     * Clés (parmi EDITABLE_FIELDS) encore vides — sert à guider l'utilisateur
     * sur ce qu'il reste précisément à compléter, plutôt que de n'afficher
     * qu'un pourcentage.
     *
     * @return list<string>
     */
    public function getMissingFreelanceProfileFields(): array
    {
        return array_keys(array_filter(
            $this->freelanceProfileFields(),
            static fn ($f) => null === $f || '' === $f,
        ));
    }

    public function eraseCredentials(): void
    {
        // Si vous stockez des données temporaires sensibles sur l'utilisateur, effacez-les ici
    }
}
