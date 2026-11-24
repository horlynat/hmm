<?php

namespace App\Entity;

use App\Entity\Traits\SlugTrait;
use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

// Pas de #[UniqueEntity(fields: ['slug'])] ici : inefficace dans ce flux —
// Symfony valide l'entité PENDANT handleRequest() (POST_SUBMIT de
// l'extension Validator), avant que AdminArticleController n'ait la main
// pour calculer le slug depuis le titre. L'unicité est garantie autrement,
// par construction, dans AdminArticleController::uniqueArticleSlug().
#[ORM\Entity(repositoryClass: ArticleRepository::class)]
class Article
{
    use SlugTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_public', 'api_admin'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['api_public', 'api_admin'])]
    private string $title = '';

    /** Titre en anglais — optionnel, retombe sur `title` (FR) côté frontend si vide. */
    #[Assert\Length(max: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $titleEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le contenu est obligatoire')]
    #[Assert\Length(min: 20, minMessage: 'Le contenu doit contenir au moins {{ limit }} caractères')]
    #[Groups(['api_public', 'api_admin'])]
    private string $content = '';

    /** Contenu en anglais — optionnel, retombe sur `content` (FR) côté frontend si vide. */
    #[Groups(['api_public', 'api_admin'])]
    private ?string $contentEn = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de publication est obligatoire')]
    #[Assert\Type(\DateTimeImmutable::class)]
    // Public : date affichée sur la fiche article (prototype `.amf`). Les
    // articles n'ont pas de brouillon ni de programmation (tout article en
    // base est déjà public) : exposer cette date ne révèle rien de plus.
    #[Groups(['api_public', 'api_admin'])]
    private \DateTimeImmutable $publishedAt;

    /** Chapô (prototype `.ald2`) : une ou deux phrases sous le titre, et dans les cartes du blog. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 400, maxMessage: 'Le chapô ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $summary = null;

    /** Chapô en anglais — optionnel, retombe sur `summary` (FR) côté frontend si vide. */
    #[Assert\Length(max: 400)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $summaryEn = null;

    /** Slug de « L'offre liée à ce sujet » (prototype `.aoff`) ; optionnel (cf. Offer::$proofProjectSlug). */
    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(max: 60)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D')]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $relatedOfferSlug = null;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'articles')]
    #[Assert\Count(min: 1, minMessage: "L'article doit avoir au moins un tag")]
    #[Groups(['api_public', 'api_admin'])]
    private Collection $tags;

    /**
     * ✅ CORRECTION : cascade: ['remove'] + orphanRemoval: true.
     *
     * cascade: ['remove'] → Doctrine supprime les Media liés avant l'Article
     * orphanRemoval: true → tout Media retiré de la collection est aussi supprimé en base
     *
     * Les deux ensemble couvrent tous les cas :
     *   - Suppression de l'article entier
     *   - Remplacement d'un media (removeMedia + addMedia)
     *
     * @var Collection<int, Media>
     */
    #[ORM\OneToMany(
        mappedBy: 'article',
        targetEntity: Media::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[Groups(['api_public', 'api_admin'])]
    private Collection $media;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->media = new ArrayCollection();
        $this->publishedAt = new \DateTimeImmutable();
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

        // $this->slug  = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
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

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getContentEn(): ?string
    {
        return $this->contentEn;
    }

    public function setContentEn(?string $contentEn): static
    {
        $this->contentEn = $contentEn;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = null !== $summary && '' !== trim($summary) ? trim($summary) : null;

        return $this;
    }

    public function getSummaryEn(): ?string
    {
        return $this->summaryEn;
    }

    public function setSummaryEn(?string $summaryEn): static
    {
        $this->summaryEn = null !== $summaryEn && '' !== trim($summaryEn) ? trim($summaryEn) : null;

        return $this;
    }

    public function getRelatedOfferSlug(): ?string
    {
        return $this->relatedOfferSlug;
    }

    public function setRelatedOfferSlug(?string $relatedOfferSlug): static
    {
        $this->relatedOfferSlug = $relatedOfferSlug;

        return $this;
    }

    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    /** @return Collection<int, Tag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
            $tag->addArticle($this);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        if ($this->tags->removeElement($tag)) {
            $tag->removeArticle($this);
        }

        return $this;
    }

    /** @return Collection<int, Media> */
    public function getMedia(): Collection
    {
        return $this->media;
    }

    public function addMedia(Media $media): static
    {
        if (!$this->media->contains($media)) {
            $this->media->add($media);
            $media->setArticle($this);
        }

        return $this;
    }

    public function removeMedia(Media $media): static
    {
        if ($this->media->removeElement($media)) {
            if ($media->getArticle() === $this) {
                $media->setArticle(null);
            }
        }

        return $this;
    }
}
