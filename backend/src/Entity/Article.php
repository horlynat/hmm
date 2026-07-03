<?php

namespace App\Entity;

use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Le titre ne peut pas dépasser {{ limit }} caractères"
    )]
    private ?string $title = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['title'])] // Génère automatiquement le slug depuis le titre
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Le contenu est obligatoire")]
    #[Assert\Length(
        min: 20,
        minMessage: "Le contenu doit contenir au moins {{ limit }} caractères"
    )]
    private ?string $content = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de publication est obligatoire")]
    #[Assert\Type(\DateTimeImmutable::class)]
    private ?\DateTimeImmutable $publishedAt = null;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'articles')] // 'articles' doit correspondre au champ dans Tag
    #[Assert\Count(
        min: 1,
        minMessage: "L'article doit avoir au moins un tag"
    )]
    private Collection $tags;

    /**
     * @var Collection<int, Media>
     */
    #[ORM\OneToMany(targetEntity: Media::class, mappedBy: 'article')]
    private Collection $media;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->media = new ArrayCollection();
        $this->publishedAt = new \DateTimeImmutable(); // auto à la création
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        // Génération manuelle simple du slug
        $this->slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
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

    /**
     * @return Collection<int, Media>
     */
    public function getMedia(): Collection
    {
        return $this->media;
    }

    public function addMedium(Media $medium): static
    {
        if (!$this->media->contains($medium)) {
            $this->media->add($medium);
            $medium->setArticle($this);
        }

        return $this;
    }

    public function removeMedium(Media $medium): static
    {
        if ($this->media->removeElement($medium)) {
            // set the owning side to null (unless already changed)
            if ($medium->getArticle() === $this) {
                $medium->setArticle(null);
            }
        }

        return $this;
    }
}
