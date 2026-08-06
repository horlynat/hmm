<?php

namespace App\Entity;

use App\Enum\ThemeEnum;
use App\Repository\SystemSettingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Configuration système globale (branding, thème, langues).
 *
 * Table à ligne unique : SystemSettingRepository::getSettings() garantit qu'une
 * seule instance existe (créée à la demande avec des valeurs par défaut).
 */
#[ORM\Entity(repositoryClass: SystemSettingRepository::class)]
class SystemSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le nom du site est obligatoire.")]
    #[Assert\Length(min: 2, max: 100)]
    private string $siteName = 'Portfolio';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoPath = null;

    #[ORM\Column(length: 7)]
    #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{6}$/', message: "La couleur doit être au format hexadécimal (#rrggbb).")]
    private string $primaryColor = '#6366f1';

    #[ORM\Column(length: 20, enumType: ThemeEnum::class)]
    private ThemeEnum $theme = ThemeEnum::AUTO;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank]
    private string $defaultLocale = 'fr';

    /** @var array<int, string> */
    #[ORM\Column(type: 'json')]
    private array $availableLocales = ['fr'];

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSiteName(): string
    {
        return $this->siteName;
    }

    public function setSiteName(string $siteName): static
    {
        $this->siteName = $siteName;

        return $this;
    }

    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function setLogoPath(?string $logoPath): static
    {
        $this->logoPath = $logoPath;

        return $this;
    }

    public function getPrimaryColor(): string
    {
        return $this->primaryColor;
    }

    public function setPrimaryColor(string $primaryColor): static
    {
        $this->primaryColor = $primaryColor;

        return $this;
    }

    public function getTheme(): ThemeEnum
    {
        return $this->theme;
    }

    public function setTheme(ThemeEnum $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    public function setDefaultLocale(string $defaultLocale): static
    {
        $this->defaultLocale = $defaultLocale;

        return $this;
    }

    /** @return array<int, string> */
    public function getAvailableLocales(): array
    {
        return $this->availableLocales;
    }

    /** @param array<int, string> $availableLocales */
    public function setAvailableLocales(array $availableLocales): static
    {
        $this->availableLocales = $availableLocales;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): static
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }
}
