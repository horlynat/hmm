<?php

namespace App\Entity;

use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_public', 'api_admin'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire")]
    #[Assert\Length(max: 255, maxMessage: "Le titre ne peut pas dépasser {{ limit }} caractères")]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $title = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['title'])]
    #[Groups(['api_public'])]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Le contenu est obligatoire")]
    #[Assert\Length(min: 20, minMessage: "Le contenu doit contenir au moins {{ limit }} caractères")]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $content = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de publication est obligatoire")]
    #[Assert\Type(\DateTimeImmutable::class)]
    #[Groups(['api_admin'])]
    private ?\DateTimeImmutable $publishedAt = null;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'articles')]
    #[Assert\Count(min: 1, minMessage: "L'article doit avoir au moins un tag")]
    #[Groups(['api_public', 'api_admin'])]
    private Collection $tags;

    /**
     * ✅ CORRECTION : cascade: ['remove'] + orphanRemoval: true
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
        $this->tags  = new ArrayCollection();
        $this->media = new ArrayCollection();
        $this->publishedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static
    {
        $this->title = $title;
        $this->slug  = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        return $this;
    }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    public function getContent(): ?string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function setPublishedAt(\DateTimeImmutable $publishedAt): static { $this->publishedAt = $publishedAt; return $this; }

    /** @return Collection<int, Tag> */
    public function getTags(): Collection { return $this->tags; }

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
    public function getMedia(): Collection { return $this->media; }

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