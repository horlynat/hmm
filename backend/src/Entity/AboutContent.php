<?php

namespace App\Entity;

use App\Repository\AboutContentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contenu narratif de la page "À propos" (hero, profil, bio, vision,
 * différenciateurs, à-côtés, appel à l'action) — bilingue, éditable en
 * back-office, consommé publiquement par l'API.
 *
 * Table à ligne unique : AboutContentRepository::getContent() garantit
 * qu'une seule instance existe (créée à la demande, cf. SystemSetting).
 */
#[ORM\Entity(repositoryClass: AboutContentRepository::class)]
class AboutContent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_public', 'api_admin'])]
    private ?int $id = null;

    // --- Hero ---

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['api_public', 'api_admin'])]
    private string $heroEyebrow = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $heroEyebrowEn = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['api_public', 'api_admin'])]
    private string $heroTitle = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $heroTitleEn = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['api_public', 'api_admin'])]
    private string $heroTitleAccent = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $heroTitleAccentEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Groups(['api_public', 'api_admin'])]
    private string $heroSub = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $heroSubEn = null;

    // --- Carte profil ---

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $profileName = '';

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $profileRole = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $profileRoleEn = null;

    #[ORM\Column(length: 100)]
    #[Groups(['api_public', 'api_admin'])]
    private string $profileAvailability = '';

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $profileAvailabilityEn = null;

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $profileAlso = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $profileAlsoEn = null;

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $profileLocation = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $profileLocationEn = null;

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $profileWorkMode = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $profileWorkModeEn = null;

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $profileLanguages = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $profileLanguagesEn = null;

    // --- Bio ---

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $bioTitle = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $bioTitleEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $bioP1 = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $bioP1En = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $bioP2 = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $bioP2En = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $bioP3 = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $bioP3En = null;

    // --- Vision ---

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $visionTitle = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $visionTitleEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $visionLede = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $visionLedeEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $visionTodayText = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $visionTodayTextEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $visionTomorrowText = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $visionTomorrowTextEn = null;

    // --- Pourquoi moi (4 cartes) ---

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $why1Title = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $why1TitleEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $why1Desc = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $why1DescEn = null;

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $why2Title = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $why2TitleEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $why2Desc = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $why2DescEn = null;

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $why3Title = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $why3TitleEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $why3Desc = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $why3DescEn = null;

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $why4Title = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $why4TitleEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $why4Desc = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $why4DescEn = null;

    // --- Au-delà du code ---

    /** @var string[] */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['api_public', 'api_admin'])]
    private array $beyondLanguages = [];

    /** @var string[]|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?array $beyondLanguagesEn = null;

    /** @var string[] */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['api_public', 'api_admin'])]
    private array $beyondInterests = [];

    /** @var string[]|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?array $beyondInterestsEn = null;

    // --- Appel à l'action final ---

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $ctaTitle = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $ctaTitleEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $ctaSub = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $ctaSubEn = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHeroEyebrow(): string
    {
        return $this->heroEyebrow;
    }

    public function setHeroEyebrow(string $heroEyebrow): static
    {
        $this->heroEyebrow = $heroEyebrow;

        return $this;
    }

    public function getHeroEyebrowEn(): ?string
    {
        return $this->heroEyebrowEn;
    }

    public function setHeroEyebrowEn(?string $heroEyebrowEn): static
    {
        $this->heroEyebrowEn = $heroEyebrowEn;

        return $this;
    }

    public function getHeroTitle(): string
    {
        return $this->heroTitle;
    }

    public function setHeroTitle(string $heroTitle): static
    {
        $this->heroTitle = $heroTitle;

        return $this;
    }

    public function getHeroTitleEn(): ?string
    {
        return $this->heroTitleEn;
    }

    public function setHeroTitleEn(?string $heroTitleEn): static
    {
        $this->heroTitleEn = $heroTitleEn;

        return $this;
    }

    public function getHeroTitleAccent(): string
    {
        return $this->heroTitleAccent;
    }

    public function setHeroTitleAccent(string $heroTitleAccent): static
    {
        $this->heroTitleAccent = $heroTitleAccent;

        return $this;
    }

    public function getHeroTitleAccentEn(): ?string
    {
        return $this->heroTitleAccentEn;
    }

    public function setHeroTitleAccentEn(?string $heroTitleAccentEn): static
    {
        $this->heroTitleAccentEn = $heroTitleAccentEn;

        return $this;
    }

    public function getHeroSub(): string
    {
        return $this->heroSub;
    }

    public function setHeroSub(string $heroSub): static
    {
        $this->heroSub = $heroSub;

        return $this;
    }

    public function getHeroSubEn(): ?string
    {
        return $this->heroSubEn;
    }

    public function setHeroSubEn(?string $heroSubEn): static
    {
        $this->heroSubEn = $heroSubEn;

        return $this;
    }

    public function getProfileName(): string
    {
        return $this->profileName;
    }

    public function setProfileName(string $profileName): static
    {
        $this->profileName = $profileName;

        return $this;
    }

    public function getProfileRole(): string
    {
        return $this->profileRole;
    }

    public function setProfileRole(string $profileRole): static
    {
        $this->profileRole = $profileRole;

        return $this;
    }

    public function getProfileRoleEn(): ?string
    {
        return $this->profileRoleEn;
    }

    public function setProfileRoleEn(?string $profileRoleEn): static
    {
        $this->profileRoleEn = $profileRoleEn;

        return $this;
    }

    public function getProfileAvailability(): string
    {
        return $this->profileAvailability;
    }

    public function setProfileAvailability(string $profileAvailability): static
    {
        $this->profileAvailability = $profileAvailability;

        return $this;
    }

    public function getProfileAvailabilityEn(): ?string
    {
        return $this->profileAvailabilityEn;
    }

    public function setProfileAvailabilityEn(?string $profileAvailabilityEn): static
    {
        $this->profileAvailabilityEn = $profileAvailabilityEn;

        return $this;
    }

    public function getProfileAlso(): string
    {
        return $this->profileAlso;
    }

    public function setProfileAlso(string $profileAlso): static
    {
        $this->profileAlso = $profileAlso;

        return $this;
    }

    public function getProfileAlsoEn(): ?string
    {
        return $this->profileAlsoEn;
    }

    public function setProfileAlsoEn(?string $profileAlsoEn): static
    {
        $this->profileAlsoEn = $profileAlsoEn;

        return $this;
    }

    public function getProfileLocation(): string
    {
        return $this->profileLocation;
    }

    public function setProfileLocation(string $profileLocation): static
    {
        $this->profileLocation = $profileLocation;

        return $this;
    }

    public function getProfileLocationEn(): ?string
    {
        return $this->profileLocationEn;
    }

    public function setProfileLocationEn(?string $profileLocationEn): static
    {
        $this->profileLocationEn = $profileLocationEn;

        return $this;
    }

    public function getProfileWorkMode(): string
    {
        return $this->profileWorkMode;
    }

    public function setProfileWorkMode(string $profileWorkMode): static
    {
        $this->profileWorkMode = $profileWorkMode;

        return $this;
    }

    public function getProfileWorkModeEn(): ?string
    {
        return $this->profileWorkModeEn;
    }

    public function setProfileWorkModeEn(?string $profileWorkModeEn): static
    {
        $this->profileWorkModeEn = $profileWorkModeEn;

        return $this;
    }

    public function getProfileLanguages(): string
    {
        return $this->profileLanguages;
    }

    public function setProfileLanguages(string $profileLanguages): static
    {
        $this->profileLanguages = $profileLanguages;

        return $this;
    }

    public function getProfileLanguagesEn(): ?string
    {
        return $this->profileLanguagesEn;
    }

    public function setProfileLanguagesEn(?string $profileLanguagesEn): static
    {
        $this->profileLanguagesEn = $profileLanguagesEn;

        return $this;
    }

    public function getBioTitle(): string
    {
        return $this->bioTitle;
    }

    public function setBioTitle(string $bioTitle): static
    {
        $this->bioTitle = $bioTitle;

        return $this;
    }

    public function getBioTitleEn(): ?string
    {
        return $this->bioTitleEn;
    }

    public function setBioTitleEn(?string $bioTitleEn): static
    {
        $this->bioTitleEn = $bioTitleEn;

        return $this;
    }

    public function getBioP1(): string
    {
        return $this->bioP1;
    }

    public function setBioP1(string $bioP1): static
    {
        $this->bioP1 = $bioP1;

        return $this;
    }

    public function getBioP1En(): ?string
    {
        return $this->bioP1En;
    }

    public function setBioP1En(?string $bioP1En): static
    {
        $this->bioP1En = $bioP1En;

        return $this;
    }

    public function getBioP2(): string
    {
        return $this->bioP2;
    }

    public function setBioP2(string $bioP2): static
    {
        $this->bioP2 = $bioP2;

        return $this;
    }

    public function getBioP2En(): ?string
    {
        return $this->bioP2En;
    }

    public function setBioP2En(?string $bioP2En): static
    {
        $this->bioP2En = $bioP2En;

        return $this;
    }

    public function getBioP3(): string
    {
        return $this->bioP3;
    }

    public function setBioP3(string $bioP3): static
    {
        $this->bioP3 = $bioP3;

        return $this;
    }

    public function getBioP3En(): ?string
    {
        return $this->bioP3En;
    }

    public function setBioP3En(?string $bioP3En): static
    {
        $this->bioP3En = $bioP3En;

        return $this;
    }

    public function getVisionTitle(): string
    {
        return $this->visionTitle;
    }

    public function setVisionTitle(string $visionTitle): static
    {
        $this->visionTitle = $visionTitle;

        return $this;
    }

    public function getVisionTitleEn(): ?string
    {
        return $this->visionTitleEn;
    }

    public function setVisionTitleEn(?string $visionTitleEn): static
    {
        $this->visionTitleEn = $visionTitleEn;

        return $this;
    }

    public function getVisionLede(): string
    {
        return $this->visionLede;
    }

    public function setVisionLede(string $visionLede): static
    {
        $this->visionLede = $visionLede;

        return $this;
    }

    public function getVisionLedeEn(): ?string
    {
        return $this->visionLedeEn;
    }

    public function setVisionLedeEn(?string $visionLedeEn): static
    {
        $this->visionLedeEn = $visionLedeEn;

        return $this;
    }

    public function getVisionTodayText(): string
    {
        return $this->visionTodayText;
    }

    public function setVisionTodayText(string $visionTodayText): static
    {
        $this->visionTodayText = $visionTodayText;

        return $this;
    }

    public function getVisionTodayTextEn(): ?string
    {
        return $this->visionTodayTextEn;
    }

    public function setVisionTodayTextEn(?string $visionTodayTextEn): static
    {
        $this->visionTodayTextEn = $visionTodayTextEn;

        return $this;
    }

    public function getVisionTomorrowText(): string
    {
        return $this->visionTomorrowText;
    }

    public function setVisionTomorrowText(string $visionTomorrowText): static
    {
        $this->visionTomorrowText = $visionTomorrowText;

        return $this;
    }

    public function getVisionTomorrowTextEn(): ?string
    {
        return $this->visionTomorrowTextEn;
    }

    public function setVisionTomorrowTextEn(?string $visionTomorrowTextEn): static
    {
        $this->visionTomorrowTextEn = $visionTomorrowTextEn;

        return $this;
    }

    public function getWhy1Title(): string
    {
        return $this->why1Title;
    }

    public function setWhy1Title(string $why1Title): static
    {
        $this->why1Title = $why1Title;

        return $this;
    }

    public function getWhy1TitleEn(): ?string
    {
        return $this->why1TitleEn;
    }

    public function setWhy1TitleEn(?string $why1TitleEn): static
    {
        $this->why1TitleEn = $why1TitleEn;

        return $this;
    }

    public function getWhy1Desc(): string
    {
        return $this->why1Desc;
    }

    public function setWhy1Desc(string $why1Desc): static
    {
        $this->why1Desc = $why1Desc;

        return $this;
    }

    public function getWhy1DescEn(): ?string
    {
        return $this->why1DescEn;
    }

    public function setWhy1DescEn(?string $why1DescEn): static
    {
        $this->why1DescEn = $why1DescEn;

        return $this;
    }

    public function getWhy2Title(): string
    {
        return $this->why2Title;
    }

    public function setWhy2Title(string $why2Title): static
    {
        $this->why2Title = $why2Title;

        return $this;
    }

    public function getWhy2TitleEn(): ?string
    {
        return $this->why2TitleEn;
    }

    public function setWhy2TitleEn(?string $why2TitleEn): static
    {
        $this->why2TitleEn = $why2TitleEn;

        return $this;
    }

    public function getWhy2Desc(): string
    {
        return $this->why2Desc;
    }

    public function setWhy2Desc(string $why2Desc): static
    {
        $this->why2Desc = $why2Desc;

        return $this;
    }

    public function getWhy2DescEn(): ?string
    {
        return $this->why2DescEn;
    }

    public function setWhy2DescEn(?string $why2DescEn): static
    {
        $this->why2DescEn = $why2DescEn;

        return $this;
    }

    public function getWhy3Title(): string
    {
        return $this->why3Title;
    }

    public function setWhy3Title(string $why3Title): static
    {
        $this->why3Title = $why3Title;

        return $this;
    }

    public function getWhy3TitleEn(): ?string
    {
        return $this->why3TitleEn;
    }

    public function setWhy3TitleEn(?string $why3TitleEn): static
    {
        $this->why3TitleEn = $why3TitleEn;

        return $this;
    }

    public function getWhy3Desc(): string
    {
        return $this->why3Desc;
    }

    public function setWhy3Desc(string $why3Desc): static
    {
        $this->why3Desc = $why3Desc;

        return $this;
    }

    public function getWhy3DescEn(): ?string
    {
        return $this->why3DescEn;
    }

    public function setWhy3DescEn(?string $why3DescEn): static
    {
        $this->why3DescEn = $why3DescEn;

        return $this;
    }

    public function getWhy4Title(): string
    {
        return $this->why4Title;
    }

    public function setWhy4Title(string $why4Title): static
    {
        $this->why4Title = $why4Title;

        return $this;
    }

    public function getWhy4TitleEn(): ?string
    {
        return $this->why4TitleEn;
    }

    public function setWhy4TitleEn(?string $why4TitleEn): static
    {
        $this->why4TitleEn = $why4TitleEn;

        return $this;
    }

    public function getWhy4Desc(): string
    {
        return $this->why4Desc;
    }

    public function setWhy4Desc(string $why4Desc): static
    {
        $this->why4Desc = $why4Desc;

        return $this;
    }

    public function getWhy4DescEn(): ?string
    {
        return $this->why4DescEn;
    }

    public function setWhy4DescEn(?string $why4DescEn): static
    {
        $this->why4DescEn = $why4DescEn;

        return $this;
    }

    /** @return string[] */
    public function getBeyondLanguages(): array
    {
        return $this->beyondLanguages;
    }

    /** @param string[] $beyondLanguages */
    public function setBeyondLanguages(array $beyondLanguages): static
    {
        $this->beyondLanguages = $beyondLanguages;

        return $this;
    }

    /** @return string[]|null */
    public function getBeyondLanguagesEn(): ?array
    {
        return $this->beyondLanguagesEn;
    }

    /** @param string[]|null $beyondLanguagesEn */
    public function setBeyondLanguagesEn(?array $beyondLanguagesEn): static
    {
        $this->beyondLanguagesEn = $beyondLanguagesEn;

        return $this;
    }

    /** @return string[] */
    public function getBeyondInterests(): array
    {
        return $this->beyondInterests;
    }

    /** @param string[] $beyondInterests */
    public function setBeyondInterests(array $beyondInterests): static
    {
        $this->beyondInterests = $beyondInterests;

        return $this;
    }

    /** @return string[]|null */
    public function getBeyondInterestsEn(): ?array
    {
        return $this->beyondInterestsEn;
    }

    /** @param string[]|null $beyondInterestsEn */
    public function setBeyondInterestsEn(?array $beyondInterestsEn): static
    {
        $this->beyondInterestsEn = $beyondInterestsEn;

        return $this;
    }

    public function getCtaTitle(): string
    {
        return $this->ctaTitle;
    }

    public function setCtaTitle(string $ctaTitle): static
    {
        $this->ctaTitle = $ctaTitle;

        return $this;
    }

    public function getCtaTitleEn(): ?string
    {
        return $this->ctaTitleEn;
    }

    public function setCtaTitleEn(?string $ctaTitleEn): static
    {
        $this->ctaTitleEn = $ctaTitleEn;

        return $this;
    }

    public function getCtaSub(): string
    {
        return $this->ctaSub;
    }

    public function setCtaSub(string $ctaSub): static
    {
        $this->ctaSub = $ctaSub;

        return $this;
    }

    public function getCtaSubEn(): ?string
    {
        return $this->ctaSubEn;
    }

    public function setCtaSubEn(?string $ctaSubEn): static
    {
        $this->ctaSubEn = $ctaSubEn;

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
