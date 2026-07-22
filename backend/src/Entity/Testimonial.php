<?php

namespace App\Entity;

use App\Repository\TestimonialRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TestimonialRepository::class)]
class Testimonial
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_public', 'api_admin'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "L'auteur est obligatoire.")]
    #[Assert\Length(max: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $author = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le contenu est obligatoire.")]
    #[Assert\Length(min: 10, max: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $content = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 0, nullable: true)]
    #[Assert\PositiveOrZero]
    #[Assert\Range(min: 0, max: 5)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $rating = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Type(\DateTimeImmutable::class)]
    #[Assert\LessThanOrEqual("today")]
    #[Groups(['api_admin'])] // exposé seulement côté admin
    private ?\DateTimeImmutable $publishedAt = null;

    /** @var Collection<int, Media> */
    #[ORM\OneToMany(targetEntity: Media::class, mappedBy: 'testimonial')]
    #[Groups(['api_public', 'api_admin'])]
    private Collection $media;

    public function __construct()
    {
        $this->media = new ArrayCollection();
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function setAuthor(string $author): static
    {
        $this->author = $author;

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

    public function getRating(): ?string
    {
        return $this->rating;
    }

    public function setRating(?string $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function isPublished(): bool
    {
        return null !== $this->publishedAt;
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
            $medium->setTestimonial($this);
        }

        return $this;
    }

    public function removeMedium(Media $medium): static
    {
        if ($this->media->removeElement($medium)) {
            // set the owning side to null (unless already changed)
            if ($medium->getTestimonial() === $this) {
                $medium->setTestimonial(null);
            }
        }

        return $this;
    }
}
