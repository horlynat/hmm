<?php

namespace App\Entity;

use App\Repository\SkillDomainRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Domaine de compétence affiché dans les onglets « Compétences » de la page
 * À propos (prototype : SK2 — icône, titre, accroche, texte, « ce que je fais
 * pour vous », outils du moment, offres liées, réalisation preuve).
 * Remplace les textes autrefois écrits en dur dans les traductions du site :
 * tout est modifiable au back-office (Contenu › Domaines de compétence).
 *
 * Bilingue par colonnes `xxxEn` (repli sur le français si vide), comme Offer.
 * Les offres et la preuve sont des slugs : le site ne les affiche que si
 * l'offre ou la réalisation correspondante est publiée.
 */
#[ORM\Entity(repositoryClass: SkillDomainRepository::class)]
class SkillDomain
{
    public const ICONS = ['arch', 'lock', 'umb', 'spark', 'app', 'web', 'cog', 'shop'];
    public const LINE_MAX = 160;
    public const TEXT_MAX = 600;
    public const SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_public'])]
    private ?int $id = null;

    /** Ordre des onglets (croissant). */
    #[ORM\Column]
    #[Assert\Range(min: 0, max: 999)]
    #[Groups(['api_public'])]
    private int $position = 0;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: self::ICONS)]
    #[Groups(['api_public'])]
    private string $icon = 'arch';

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: self::LINE_MAX)]
    #[Assert\Regex(pattern: Offer::SAFE_LINE, message: 'Caractère invisible ou de contrôle interdit.')]
    #[Groups(['api_public'])]
    private string $title = '';

    #[ORM\Column(length: 160, nullable: true)]
    #[Assert\Length(max: self::LINE_MAX)]
    #[Assert\Regex(pattern: Offer::SAFE_LINE, message: 'Caractère invisible ou de contrôle interdit.')]
    #[Groups(['api_public'])]
    private ?string $titleEn = null;

    /** Accroche courte sous le titre de l'onglet. */
    #[ORM\Column(length: 160)]
    #[Assert\Length(max: self::LINE_MAX)]
    #[Assert\Regex(pattern: Offer::SAFE_LINE, message: 'Caractère invisible ou de contrôle interdit.')]
    #[Groups(['api_public'])]
    private string $short = '';

    #[ORM\Column(length: 160, nullable: true)]
    #[Assert\Length(max: self::LINE_MAX)]
    #[Assert\Regex(pattern: Offer::SAFE_LINE, message: 'Caractère invisible ou de contrôle interdit.')]
    #[Groups(['api_public'])]
    private ?string $shortEn = null;

    /** Paragraphe de présentation du domaine. */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\Length(max: self::TEXT_MAX)]
    #[Assert\Regex(pattern: Offer::SAFE_TEXT, message: 'Caractère invisible ou de contrôle interdit.')]
    #[Groups(['api_public'])]
    private string $text = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: self::TEXT_MAX)]
    #[Assert\Regex(pattern: Offer::SAFE_TEXT, message: 'Caractère invisible ou de contrôle interdit.')]
    #[Groups(['api_public'])]
    private ?string $textEn = null;

    /** @var list<string> « Ce que je fais pour vous ». */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Type('list')]
    #[Assert\Count(max: 8)]
    #[Assert\All([new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: self::LINE_MAX), new Assert\Regex(pattern: Offer::SAFE_LINE, message: 'Caractère invisible ou de contrôle interdit.')])])]
    #[Groups(['api_public'])]
    private array $doItems = [];

    /** @var list<string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Assert\Type('list')]
    #[Assert\Count(max: 8)]
    #[Assert\All([new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: self::LINE_MAX), new Assert\Regex(pattern: Offer::SAFE_LINE, message: 'Caractère invisible ou de contrôle interdit.')])])]
    #[Groups(['api_public'])]
    private ?array $doItemsEn = null;

    /** @var list<string> « Outils du moment » (pastilles). */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Type('list')]
    #[Assert\Count(max: 20)]
    #[Assert\All([new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 60), new Assert\Regex(pattern: Offer::SAFE_LINE, message: 'Caractère invisible ou de contrôle interdit.')])])]
    #[Groups(['api_public'])]
    private array $tools = [];

    /** @var list<string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Assert\Type('list')]
    #[Assert\Count(max: 20)]
    #[Assert\All([new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 60), new Assert\Regex(pattern: Offer::SAFE_LINE, message: 'Caractère invisible ou de contrôle interdit.')])])]
    #[Groups(['api_public'])]
    private ?array $toolsEn = null;

    /** @var list<string> Slugs des offres liées (affichées seulement si publiées). */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Type('list')]
    #[Assert\Count(max: 4)]
    #[Assert\All([new Assert\Sequentially([new Assert\Type('string'), new Assert\Length(max: 120), new Assert\Regex(pattern: self::SLUG, message: 'Identifiant d\'offre invalide.')])])]
    #[Groups(['api_public'])]
    private array $offerSlugs = [];

    /** Slug de la réalisation qui sert de preuve (affichée seulement si publique). */
    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    #[Assert\Regex(pattern: self::SLUG, message: 'Identifiant de réalisation invalide.')]
    #[Groups(['api_public'])]
    private ?string $proofProjectSlug = null;

    public function getId(): ?int { return $this->id; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(?int $v): static { $this->position = $v ?? 0; return $this; }

    public function getIcon(): string { return $this->icon; }
    public function setIcon(?string $v): static { $this->icon = $v ?? 'arch'; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(?string $v): static { $this->title = $v ?? ''; return $this; }
    public function getTitleEn(): ?string { return $this->titleEn; }
    public function setTitleEn(?string $v): static { $this->titleEn = '' === trim((string) $v) ? null : $v; return $this; }

    public function getShort(): string { return $this->short; }
    public function setShort(?string $v): static { $this->short = $v ?? ''; return $this; }
    public function getShortEn(): ?string { return $this->shortEn; }
    public function setShortEn(?string $v): static { $this->shortEn = '' === trim((string) $v) ? null : $v; return $this; }

    public function getText(): string { return $this->text; }
    public function setText(?string $v): static { $this->text = $v ?? ''; return $this; }
    public function getTextEn(): ?string { return $this->textEn; }
    public function setTextEn(?string $v): static { $this->textEn = '' === trim((string) $v) ? null : $v; return $this; }

    /** @return list<string> */
    public function getDoItems(): array { return $this->doItems; }
    /** @param list<string>|null $v */
    public function setDoItems(?array $v): static { $this->doItems = $v ?? []; return $this; }
    /** @return list<string>|null */
    public function getDoItemsEn(): ?array { return $this->doItemsEn; }
    /** @param list<string>|null $v */
    public function setDoItemsEn(?array $v): static { $this->doItemsEn = [] === $v ? null : $v; return $this; }

    /** @return list<string> */
    public function getTools(): array { return $this->tools; }
    /** @param list<string>|null $v */
    public function setTools(?array $v): static { $this->tools = $v ?? []; return $this; }
    /** @return list<string>|null */
    public function getToolsEn(): ?array { return $this->toolsEn; }
    /** @param list<string>|null $v */
    public function setToolsEn(?array $v): static { $this->toolsEn = [] === $v ? null : $v; return $this; }

    /** @return list<string> */
    public function getOfferSlugs(): array { return $this->offerSlugs; }
    /** @param list<string>|null $v */
    public function setOfferSlugs(?array $v): static { $this->offerSlugs = $v ?? []; return $this; }

    public function getProofProjectSlug(): ?string { return $this->proofProjectSlug; }
    public function setProofProjectSlug(?string $v): static { $this->proofProjectSlug = '' === trim((string) $v) ? null : trim((string) $v); return $this; }
}
