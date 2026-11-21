<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\OfferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Offre commerciale du site public (refonte 2026-11) : carrousel et cartes de
 * l'accueil, pages « Offres ». Modèle de contenu tiré du prototype de refonte.
 *
 * Écriture : back-office Twig uniquement (AdminOfferController, ROLE_ADMIN +
 * CSRF) ; l'API n'expose que la lecture des offres publiées
 * (OfferApiResource, PublishedOfferExtension).
 *
 * Champs bilingues : la version française est obligatoire, l'anglaise
 * optionnelle (le frontend retombe sur le français si elle est vide), même
 * convention que Experience / AboutContent.
 *
 * Toute valeur est bornée (longueur alignée sur la colonne, listes de taille
 * et de forme contrôlées, contraintes en séquence) : leçon du lot B3, où des
 * champs non bornés donnaient des 500.
 */
#[ORM\Entity(repositoryClass: OfferRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'Ce slug est déjà utilisé par une autre offre.')]
class Offer
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    public const CATEGORIES = ['dev', 'aud'];
    public const ICONS = ['shop', 'web', 'lock', 'cog', 'app', 'arch', 'umb'];
    public const PREVIEWS = ['chat', 'screens', 'report', 'flow', 'roadmap', 'risk'];
    public const SITUATIONS = ['clients', 'appli', 'secu', 'assur'];

    public const MAX_PRICE = 1_000_000_000;
    public const LIST_ITEM_MAX = 200;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['offer_public'])]
    private ?int $id = null;

    #[ORM\Column(length: 40, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 40)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', message: 'Minuscules, chiffres et tirets uniquement (ex. assistant-whatsapp).')]
    #[Groups(['offer_public'])]
    private string $slug = '';

    /** Ordre d'affichage dans les listes (croissant). */
    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    #[Assert\Range(min: 0, max: 999)]
    #[Groups(['offer_public'])]
    private int $position = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $published = false;

    /** Position dans le carrousel de l'accueil ; null = absente du carrousel. */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(min: 1, max: 20)]
    #[Groups(['offer_public'])]
    private ?int $heroPosition = null;

    /** Offre mise en avant (badge « vedette »). */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['offer_public'])]
    private bool $featured = false;

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: self::CATEGORIES)]
    #[Groups(['offer_public'])]
    private string $category = 'dev';

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: self::ICONS)]
    #[Groups(['offer_public'])]
    private string $icon = 'web';

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: self::PREVIEWS)]
    #[Groups(['offer_public'])]
    private string $previewType = 'screens';

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: self::SITUATIONS)]
    #[Groups(['offer_public'])]
    private string $situation = 'clients';

    /** Slug du projet (Réalisations) cité en preuve ; optionnel. */
    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(max: 60)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D')]
    #[Groups(['offer_public'])]
    private ?string $proofProjectSlug = null;

    /** Prix en FCFA (entier, sans décimales). */
    #[ORM\Column]
    #[Assert\Range(min: 0, max: self::MAX_PRICE)]
    #[Groups(['offer_public'])]
    private int $price = 0;

    /** true = « Prix unique », false = « À partir de ». */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['offer_public'])]
    private bool $priceOnce = false;

    // ---- Textes bilingues -------------------------------------------------

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['offer_public'])]
    private string $title = '';

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    #[Groups(['offer_public'])]
    private ?string $titleEn = null;

    /** Libellé court (points du carrousel, menus). */
    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 40)]
    #[Groups(['offer_public'])]
    private string $shortTitle = '';

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    #[Groups(['offer_public'])]
    private ?string $shortTitleEn = null;

    /** Douleur du client (« Vos clients écrivent le soir… ? »). */
    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    #[Groups(['offer_public'])]
    private string $pain = '';

    #[ORM\Column(length: 200, nullable: true)]
    #[Assert\Length(max: 200)]
    #[Groups(['offer_public'])]
    private ?string $painEn = null;

    /** Titre du carrousel et de la page offre. */
    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    #[Groups(['offer_public'])]
    private string $headline = '';

    #[ORM\Column(length: 200, nullable: true)]
    #[Assert\Length(max: 200)]
    #[Groups(['offer_public'])]
    private ?string $headlineEn = null;

    // `lead` est un mot réservé de MySQL 8 (fonction de fenêtrage) : d'où leadText.
    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    #[Groups(['offer_public'])]
    private string $leadText = '';

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    #[Groups(['offer_public'])]
    private ?string $leadTextEn = null;

    /** Phrase de la carte offre (bénéfice). */
    #[ORM\Column(length: 300)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 300)]
    #[Groups(['offer_public'])]
    private string $cardText = '';

    #[ORM\Column(length: 300, nullable: true)]
    #[Assert\Length(max: 300)]
    #[Groups(['offer_public'])]
    private ?string $cardTextEn = null;

    /** Pour qui (« Commerces, boutiques… »). */
    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    #[Groups(['offer_public'])]
    private string $audience = '';

    #[ORM\Column(length: 200, nullable: true)]
    #[Assert\Length(max: 200)]
    #[Groups(['offer_public'])]
    private ?string $audienceEn = null;

    /** Ce dont j'ai besoin de la part du client. */
    #[ORM\Column(length: 400)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 400)]
    #[Groups(['offer_public'])]
    private string $clientNeeds = '';

    #[ORM\Column(length: 400, nullable: true)]
    #[Assert\Length(max: 400)]
    #[Groups(['offer_public'])]
    private ?string $clientNeedsEn = null;

    /** Délai affiché (« 21 jours »). */
    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 40)]
    #[Groups(['offer_public'])]
    private string $delay = '';

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    #[Groups(['offer_public'])]
    private ?string $delayEn = null;

    // ---- Listes bilingues (JSON) ------------------------------------------
    // Forme contrôlée : `list` (jamais un objet JSON), taille bornée, chaque
    // élément vérifié en séquence (le type d'abord : trim() / Length sur un
    // non-string serait un TypeError, donc une 500).

    /** @var list<string> Ce que le client reçoit. */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Type('list')]
    #[Assert\Count(min: 1, max: 12)]
    #[Assert\All([new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: self::LIST_ITEM_MAX)])])]
    #[Groups(['offer_public'])]
    private array $includes = [];

    /** @var list<string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Assert\Type('list')]
    #[Assert\Count(max: 12)]
    #[Assert\All([new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: self::LIST_ITEM_MAX)])])]
    #[Groups(['offer_public'])]
    private ?array $includesEn = null;

    /** @var list<string> « Aujourd'hui » (avant l'offre). */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Type('list')]
    #[Assert\Count(max: 6)]
    #[Assert\All([new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: self::LIST_ITEM_MAX)])])]
    #[Groups(['offer_public'])]
    private array $beforeItems = [];

    /** @var list<string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Assert\Type('list')]
    #[Assert\Count(max: 6)]
    #[Assert\All([new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: self::LIST_ITEM_MAX)])])]
    #[Groups(['offer_public'])]
    private ?array $beforeItemsEn = null;

    /** @var list<string> « Avec cette offre ». */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Type('list')]
    #[Assert\Count(max: 6)]
    #[Assert\All([new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: self::LIST_ITEM_MAX)])])]
    #[Groups(['offer_public'])]
    private array $afterItems = [];

    /** @var list<string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Assert\Type('list')]
    #[Assert\Count(max: 6)]
    #[Assert\All([new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: self::LIST_ITEM_MAX)])])]
    #[Groups(['offer_public'])]
    private ?array $afterItemsEn = null;

    /** @var list<array{day: string, text: string}> Calendrier (J1, J3…). */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Type('list')]
    #[Assert\Count(max: 12)]
    #[Assert\All([new Assert\Sequentially([new Assert\Type('array'), new Assert\Collection(fields: [
        'day' => new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 10)]),
        'text' => new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: self::LIST_ITEM_MAX)]),
    ])])])]
    #[Groups(['offer_public'])]
    private array $calendar = [];

    /** @var list<array{day: string, text: string}>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Assert\Type('list')]
    #[Assert\Count(max: 12)]
    #[Assert\All([new Assert\Sequentially([new Assert\Type('array'), new Assert\Collection(fields: [
        'day' => new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 10)]),
        'text' => new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: self::LIST_ITEM_MAX)]),
    ])])])]
    #[Groups(['offer_public'])]
    private ?array $calendarEn = null;

    /** @var list<array{title: string, items: list<string>}> Blocs du pack (« Répondre », « Vendre »…). */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Type('list')]
    #[Assert\Count(max: 5)]
    #[Assert\All([new Assert\Sequentially([new Assert\Type('array'), new Assert\Collection(fields: [
        'title' => new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 60)]),
        'items' => new Assert\Sequentially([new Assert\Type('list'), new Assert\Count(min: 1, max: 8), new Assert\All([new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: self::LIST_ITEM_MAX)])])]),
    ])])])]
    #[Groups(['offer_public'])]
    private array $pack = [];

    /** @var list<array{title: string, items: list<string>}>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Assert\Type('list')]
    #[Assert\Count(max: 5)]
    #[Assert\All([new Assert\Sequentially([new Assert\Type('array'), new Assert\Collection(fields: [
        'title' => new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 60)]),
        'items' => new Assert\Sequentially([new Assert\Type('list'), new Assert\Count(min: 1, max: 8), new Assert\All([new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: self::LIST_ITEM_MAX)])])]),
    ])])])]
    #[Groups(['offer_public'])]
    private ?array $packEn = null;

    public function getId(): ?int { return $this->id; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }

    public function isPublished(): bool { return $this->published; }
    public function setPublished(bool $published): static { $this->published = $published; return $this; }

    public function getHeroPosition(): ?int { return $this->heroPosition; }
    public function setHeroPosition(?int $heroPosition): static { $this->heroPosition = $heroPosition; return $this; }

    public function isFeatured(): bool { return $this->featured; }
    public function setFeatured(bool $featured): static { $this->featured = $featured; return $this; }

    public function getCategory(): string { return $this->category; }
    public function setCategory(string $category): static { $this->category = $category; return $this; }

    public function getIcon(): string { return $this->icon; }
    public function setIcon(string $icon): static { $this->icon = $icon; return $this; }

    public function getPreviewType(): string { return $this->previewType; }
    public function setPreviewType(string $previewType): static { $this->previewType = $previewType; return $this; }

    public function getSituation(): string { return $this->situation; }
    public function setSituation(string $situation): static { $this->situation = $situation; return $this; }

    public function getProofProjectSlug(): ?string { return $this->proofProjectSlug; }
    public function setProofProjectSlug(?string $slug): static { $this->proofProjectSlug = $slug; return $this; }

    public function getPrice(): int { return $this->price; }
    public function setPrice(int $price): static { $this->price = $price; return $this; }

    public function isPriceOnce(): bool { return $this->priceOnce; }
    public function setPriceOnce(bool $priceOnce): static { $this->priceOnce = $priceOnce; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): static { $this->title = $v; return $this; }
    public function getTitleEn(): ?string { return $this->titleEn; }
    public function setTitleEn(?string $v): static { $this->titleEn = $v; return $this; }

    public function getShortTitle(): string { return $this->shortTitle; }
    public function setShortTitle(string $v): static { $this->shortTitle = $v; return $this; }
    public function getShortTitleEn(): ?string { return $this->shortTitleEn; }
    public function setShortTitleEn(?string $v): static { $this->shortTitleEn = $v; return $this; }

    public function getPain(): string { return $this->pain; }
    public function setPain(string $v): static { $this->pain = $v; return $this; }
    public function getPainEn(): ?string { return $this->painEn; }
    public function setPainEn(?string $v): static { $this->painEn = $v; return $this; }

    public function getHeadline(): string { return $this->headline; }
    public function setHeadline(string $v): static { $this->headline = $v; return $this; }
    public function getHeadlineEn(): ?string { return $this->headlineEn; }
    public function setHeadlineEn(?string $v): static { $this->headlineEn = $v; return $this; }

    public function getLeadText(): string { return $this->leadText; }
    public function setLeadText(string $v): static { $this->leadText = $v; return $this; }
    public function getLeadTextEn(): ?string { return $this->leadTextEn; }
    public function setLeadTextEn(?string $v): static { $this->leadTextEn = $v; return $this; }

    public function getCardText(): string { return $this->cardText; }
    public function setCardText(string $v): static { $this->cardText = $v; return $this; }
    public function getCardTextEn(): ?string { return $this->cardTextEn; }
    public function setCardTextEn(?string $v): static { $this->cardTextEn = $v; return $this; }

    public function getAudience(): string { return $this->audience; }
    public function setAudience(string $v): static { $this->audience = $v; return $this; }
    public function getAudienceEn(): ?string { return $this->audienceEn; }
    public function setAudienceEn(?string $v): static { $this->audienceEn = $v; return $this; }

    public function getClientNeeds(): string { return $this->clientNeeds; }
    public function setClientNeeds(string $v): static { $this->clientNeeds = $v; return $this; }
    public function getClientNeedsEn(): ?string { return $this->clientNeedsEn; }
    public function setClientNeedsEn(?string $v): static { $this->clientNeedsEn = $v; return $this; }

    public function getDelay(): string { return $this->delay; }
    public function setDelay(string $v): static { $this->delay = $v; return $this; }
    public function getDelayEn(): ?string { return $this->delayEn; }
    public function setDelayEn(?string $v): static { $this->delayEn = $v; return $this; }

    /** @return list<string> */
    public function getIncludes(): array { return $this->includes; }
    /** @param list<string> $v */
    public function setIncludes(array $v): static { $this->includes = $v; return $this; }
    /** @return list<string>|null */
    public function getIncludesEn(): ?array { return $this->includesEn; }
    /** @param list<string>|null $v */
    public function setIncludesEn(?array $v): static { $this->includesEn = $v; return $this; }

    /** @return list<string> */
    public function getBeforeItems(): array { return $this->beforeItems; }
    /** @param list<string> $v */
    public function setBeforeItems(array $v): static { $this->beforeItems = $v; return $this; }
    /** @return list<string>|null */
    public function getBeforeItemsEn(): ?array { return $this->beforeItemsEn; }
    /** @param list<string>|null $v */
    public function setBeforeItemsEn(?array $v): static { $this->beforeItemsEn = $v; return $this; }

    /** @return list<string> */
    public function getAfterItems(): array { return $this->afterItems; }
    /** @param list<string> $v */
    public function setAfterItems(array $v): static { $this->afterItems = $v; return $this; }
    /** @return list<string>|null */
    public function getAfterItemsEn(): ?array { return $this->afterItemsEn; }
    /** @param list<string>|null $v */
    public function setAfterItemsEn(?array $v): static { $this->afterItemsEn = $v; return $this; }

    /** @return list<array{day: string, text: string}> */
    public function getCalendar(): array { return $this->calendar; }
    /** @param list<array{day: string, text: string}> $v */
    public function setCalendar(array $v): static { $this->calendar = $v; return $this; }
    /** @return list<array{day: string, text: string}>|null */
    public function getCalendarEn(): ?array { return $this->calendarEn; }
    /** @param list<array{day: string, text: string}>|null $v */
    public function setCalendarEn(?array $v): static { $this->calendarEn = $v; return $this; }

    /** @return list<array{title: string, items: list<string>}> */
    public function getPack(): array { return $this->pack; }
    /** @param list<array{title: string, items: list<string>}> $v */
    public function setPack(array $v): static { $this->pack = $v; return $this; }
    /** @return list<array{title: string, items: list<string>}>|null */
    public function getPackEn(): ?array { return $this->packEn; }
    /** @param list<array{title: string, items: list<string>}>|null $v */
    public function setPackEn(?array $v): static { $this->packEn = $v; return $this; }
}
