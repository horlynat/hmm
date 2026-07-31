<?php

namespace App\Entity;

use App\Repository\HomeContentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contenu narratif de la page d'accueil (hero, teaser "à propos", pitch
 * freelance, appel à l'action final) — bilingue, éditable en back-office,
 * consommé publiquement par l'API pour que le frontend et l'assistant IA
 * partagent la même source de vérité.
 *
 * Table à ligne unique : HomeContentRepository::getContent() garantit
 * qu'une seule instance existe (créée à la demande, cf. SystemSetting).
 */
#[ORM\Entity(repositoryClass: HomeContentRepository::class)]
class HomeContent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_public', 'api_admin'])]
    private ?int $id = null;

    // --- Hero ---

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "L'accroche du hero est obligatoire.")]
    #[Groups(['api_public', 'api_admin'])]
    private string $heroEyebrow = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $heroEyebrowEn = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre du hero est obligatoire.")]
    #[Groups(['api_public', 'api_admin'])]
    private string $heroTitle = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $heroTitleEn = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "L'accent du titre du hero est obligatoire.")]
    #[Groups(['api_public', 'api_admin'])]
    private string $heroTitleAccent = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $heroTitleAccentEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Le sous-titre du hero est obligatoire.")]
    #[Groups(['api_public', 'api_admin'])]
    private string $heroSub = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $heroSubEn = null;

    /**
     * Rôles défilants affichés sous le titre.
     *
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['api_public', 'api_admin'])]
    private array $heroRoles = [];

    /** @var string[]|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?array $heroRolesEn = null;

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $founderBadge = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $founderBadgeEn = null;

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $diagramCaption = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $diagramCaptionEn = null;

    // --- Teaser "à propos" ---

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $aboutTitle = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $aboutTitleEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $aboutP1 = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $aboutP1En = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $aboutP2 = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $aboutP2En = null;

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $aboutHighlightTitle = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $aboutHighlightTitleEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $aboutHighlightDesc = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $aboutHighlightDescEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $aboutVisionText = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $aboutVisionTextEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $aboutMissionText = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $aboutMissionTextEn = null;

    // --- Pitch freelance ---

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $freelanceTitle = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $freelanceTitleEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $freelanceLede = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $freelanceLedeEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $freelancePoint1 = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $freelancePoint1En = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $freelancePoint2 = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $freelancePoint2En = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $freelancePoint3 = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $freelancePoint3En = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $freelanceCardDesc = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $freelanceCardDescEn = null;

    // --- Appel à l'action final ---

    #[ORM\Column(length: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $contactCtaTitle = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $contactCtaTitleEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api_public', 'api_admin'])]
    private string $contactCtaSub = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $contactCtaSubEn = null;

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

    /** @return string[] */
    public function getHeroRoles(): array
    {
        return $this->heroRoles;
    }

    /** @param string[] $heroRoles */
    public function setHeroRoles(array $heroRoles): static
    {
        $this->heroRoles = $heroRoles;

        return $this;
    }

    /** @return string[]|null */
    public function getHeroRolesEn(): ?array
    {
        return $this->heroRolesEn;
    }

    /** @param string[]|null $heroRolesEn */
    public function setHeroRolesEn(?array $heroRolesEn): static
    {
        $this->heroRolesEn = $heroRolesEn;

        return $this;
    }

    public function getFounderBadge(): string
    {
        return $this->founderBadge;
    }

    public function setFounderBadge(string $founderBadge): static
    {
        $this->founderBadge = $founderBadge;

        return $this;
    }

    public function getFounderBadgeEn(): ?string
    {
        return $this->founderBadgeEn;
    }

    public function setFounderBadgeEn(?string $founderBadgeEn): static
    {
        $this->founderBadgeEn = $founderBadgeEn;

        return $this;
    }

    public function getDiagramCaption(): string
    {
        return $this->diagramCaption;
    }

    public function setDiagramCaption(string $diagramCaption): static
    {
        $this->diagramCaption = $diagramCaption;

        return $this;
    }

    public function getDiagramCaptionEn(): ?string
    {
        return $this->diagramCaptionEn;
    }

    public function setDiagramCaptionEn(?string $diagramCaptionEn): static
    {
        $this->diagramCaptionEn = $diagramCaptionEn;

        return $this;
    }

    public function getAboutTitle(): string
    {
        return $this->aboutTitle;
    }

    public function setAboutTitle(string $aboutTitle): static
    {
        $this->aboutTitle = $aboutTitle;

        return $this;
    }

    public function getAboutTitleEn(): ?string
    {
        return $this->aboutTitleEn;
    }

    public function setAboutTitleEn(?string $aboutTitleEn): static
    {
        $this->aboutTitleEn = $aboutTitleEn;

        return $this;
    }

    public function getAboutP1(): string
    {
        return $this->aboutP1;
    }

    public function setAboutP1(string $aboutP1): static
    {
        $this->aboutP1 = $aboutP1;

        return $this;
    }

    public function getAboutP1En(): ?string
    {
        return $this->aboutP1En;
    }

    public function setAboutP1En(?string $aboutP1En): static
    {
        $this->aboutP1En = $aboutP1En;

        return $this;
    }

    public function getAboutP2(): string
    {
        return $this->aboutP2;
    }

    public function setAboutP2(string $aboutP2): static
    {
        $this->aboutP2 = $aboutP2;

        return $this;
    }

    public function getAboutP2En(): ?string
    {
        return $this->aboutP2En;
    }

    public function setAboutP2En(?string $aboutP2En): static
    {
        $this->aboutP2En = $aboutP2En;

        return $this;
    }

    public function getAboutHighlightTitle(): string
    {
        return $this->aboutHighlightTitle;
    }

    public function setAboutHighlightTitle(string $aboutHighlightTitle): static
    {
        $this->aboutHighlightTitle = $aboutHighlightTitle;

        return $this;
    }

    public function getAboutHighlightTitleEn(): ?string
    {
        return $this->aboutHighlightTitleEn;
    }

    public function setAboutHighlightTitleEn(?string $aboutHighlightTitleEn): static
    {
        $this->aboutHighlightTitleEn = $aboutHighlightTitleEn;

        return $this;
    }

    public function getAboutHighlightDesc(): string
    {
        return $this->aboutHighlightDesc;
    }

    public function setAboutHighlightDesc(string $aboutHighlightDesc): static
    {
        $this->aboutHighlightDesc = $aboutHighlightDesc;

        return $this;
    }

    public function getAboutHighlightDescEn(): ?string
    {
        return $this->aboutHighlightDescEn;
    }

    public function setAboutHighlightDescEn(?string $aboutHighlightDescEn): static
    {
        $this->aboutHighlightDescEn = $aboutHighlightDescEn;

        return $this;
    }

    public function getAboutVisionText(): string
    {
        return $this->aboutVisionText;
    }

    public function setAboutVisionText(string $aboutVisionText): static
    {
        $this->aboutVisionText = $aboutVisionText;

        return $this;
    }

    public function getAboutVisionTextEn(): ?string
    {
        return $this->aboutVisionTextEn;
    }

    public function setAboutVisionTextEn(?string $aboutVisionTextEn): static
    {
        $this->aboutVisionTextEn = $aboutVisionTextEn;

        return $this;
    }

    public function getAboutMissionText(): string
    {
        return $this->aboutMissionText;
    }

    public function setAboutMissionText(string $aboutMissionText): static
    {
        $this->aboutMissionText = $aboutMissionText;

        return $this;
    }

    public function getAboutMissionTextEn(): ?string
    {
        return $this->aboutMissionTextEn;
    }

    public function setAboutMissionTextEn(?string $aboutMissionTextEn): static
    {
        $this->aboutMissionTextEn = $aboutMissionTextEn;

        return $this;
    }

    public function getFreelanceTitle(): string
    {
        return $this->freelanceTitle;
    }

    public function setFreelanceTitle(string $freelanceTitle): static
    {
        $this->freelanceTitle = $freelanceTitle;

        return $this;
    }

    public function getFreelanceTitleEn(): ?string
    {
        return $this->freelanceTitleEn;
    }

    public function setFreelanceTitleEn(?string $freelanceTitleEn): static
    {
        $this->freelanceTitleEn = $freelanceTitleEn;

        return $this;
    }

    public function getFreelanceLede(): string
    {
        return $this->freelanceLede;
    }

    public function setFreelanceLede(string $freelanceLede): static
    {
        $this->freelanceLede = $freelanceLede;

        return $this;
    }

    public function getFreelanceLedeEn(): ?string
    {
        return $this->freelanceLedeEn;
    }

    public function setFreelanceLedeEn(?string $freelanceLedeEn): static
    {
        $this->freelanceLedeEn = $freelanceLedeEn;

        return $this;
    }

    public function getFreelancePoint1(): string
    {
        return $this->freelancePoint1;
    }

    public function setFreelancePoint1(string $freelancePoint1): static
    {
        $this->freelancePoint1 = $freelancePoint1;

        return $this;
    }

    public function getFreelancePoint1En(): ?string
    {
        return $this->freelancePoint1En;
    }

    public function setFreelancePoint1En(?string $freelancePoint1En): static
    {
        $this->freelancePoint1En = $freelancePoint1En;

        return $this;
    }

    public function getFreelancePoint2(): string
    {
        return $this->freelancePoint2;
    }

    public function setFreelancePoint2(string $freelancePoint2): static
    {
        $this->freelancePoint2 = $freelancePoint2;

        return $this;
    }

    public function getFreelancePoint2En(): ?string
    {
        return $this->freelancePoint2En;
    }

    public function setFreelancePoint2En(?string $freelancePoint2En): static
    {
        $this->freelancePoint2En = $freelancePoint2En;

        return $this;
    }

    public function getFreelancePoint3(): string
    {
        return $this->freelancePoint3;
    }

    public function setFreelancePoint3(string $freelancePoint3): static
    {
        $this->freelancePoint3 = $freelancePoint3;

        return $this;
    }

    public function getFreelancePoint3En(): ?string
    {
        return $this->freelancePoint3En;
    }

    public function setFreelancePoint3En(?string $freelancePoint3En): static
    {
        $this->freelancePoint3En = $freelancePoint3En;

        return $this;
    }

    public function getFreelanceCardDesc(): string
    {
        return $this->freelanceCardDesc;
    }

    public function setFreelanceCardDesc(string $freelanceCardDesc): static
    {
        $this->freelanceCardDesc = $freelanceCardDesc;

        return $this;
    }

    public function getFreelanceCardDescEn(): ?string
    {
        return $this->freelanceCardDescEn;
    }

    public function setFreelanceCardDescEn(?string $freelanceCardDescEn): static
    {
        $this->freelanceCardDescEn = $freelanceCardDescEn;

        return $this;
    }

    public function getContactCtaTitle(): string
    {
        return $this->contactCtaTitle;
    }

    public function setContactCtaTitle(string $contactCtaTitle): static
    {
        $this->contactCtaTitle = $contactCtaTitle;

        return $this;
    }

    public function getContactCtaTitleEn(): ?string
    {
        return $this->contactCtaTitleEn;
    }

    public function setContactCtaTitleEn(?string $contactCtaTitleEn): static
    {
        $this->contactCtaTitleEn = $contactCtaTitleEn;

        return $this;
    }

    public function getContactCtaSub(): string
    {
        return $this->contactCtaSub;
    }

    public function setContactCtaSub(string $contactCtaSub): static
    {
        $this->contactCtaSub = $contactCtaSub;

        return $this;
    }

    public function getContactCtaSubEn(): ?string
    {
        return $this->contactCtaSubEn;
    }

    public function setContactCtaSubEn(?string $contactCtaSubEn): static
    {
        $this->contactCtaSubEn = $contactCtaSubEn;

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
