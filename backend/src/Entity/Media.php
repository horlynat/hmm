<?php

namespace App\Entity;

use App\Repository\MediaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
class Media
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le chemin du fichier est obligatoire.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Le chemin du fichier ne peut pas dépasser {{ limit }} caractères."
    )]
    private ?string $filePath = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le texte alternatif est obligatoire.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Le texte alternatif ne peut pas dépasser {{ limit }} caractères."
    )]
    private ?string $altText = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: "Le type de média est obligatoire.")]
    #[Assert\Choice(
        choices: ["image", "video", "audio", "document"],
        message: "Le type doit être l'un des suivants : image, video, audio, document."
    )]
    private ?string $type = null;

    #[ORM\ManyToOne(inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Le média doit être associé à un article.")]
    private ?Article $article = null;

    #[ORM\ManyToOne(inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Le média doit être associé à un projet.")]
    private ?Project $project = null;

    #[ORM\ManyToOne(inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Le média doit être associé à un témoignage.")]
    private ?Testimonial $testimonial = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function setAltText(string $altText): static
    {
        $this->altText = $altText;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): static
    {
        $this->article = $article;
        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;
        return $this;
    }

    public function getTestimonial(): ?Testimonial
    {
        return $this->testimonial;
    }

    public function setTestimonial(?Testimonial $testimonial): static
    {
        $this->testimonial = $testimonial;
        return $this;
    }
}
