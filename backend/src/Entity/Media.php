<?php

namespace App\Entity;

use App\Repository\MediaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
class Media
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_public', 'api_admin'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le chemin du fichier est obligatoire.")]
    #[Assert\Length(max: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $filePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $altText = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $mimeType = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?int $size = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?\DateTimeImmutable $uploadedAt = null;

    /**
     * ✅ CORRECTION 1 : type auto-détecté depuis le mimeType → nullable: true
     * Plus besoin de le passer manuellement dans le controller.
     */
    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(choices: ['image', 'video', 'audio', 'document'])]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $type = null;

    /**
     * ✅ CORRECTION 2 : toutes les relations nullable: true
     * Un Media appartient à UN SEUL des trois. Les deux autres seront null → autorisé.
     */
    #[ORM\ManyToOne(inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['api_admin'])]
    private ?Article $article = null;

    #[ORM\ManyToOne(inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['api_admin'])]
    private ?Project $project = null;

    #[ORM\ManyToOne(inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['api_admin'])]
    private ?Testimonial $testimonial = null;

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getFilePath(): ?string { return $this->filePath; }
    public function setFilePath(string $filePath): static { $this->filePath = $filePath; return $this; }

    public function getAltText(): ?string { return $this->altText; }
    public function setAltText(?string $altText): static { $this->altText = $altText; return $this; }

    public function getMimeType(): ?string { return $this->mimeType; }
    public function setMimeType(?string $mimeType): static
    {
        $this->mimeType = $mimeType;
        // ✅ Auto-déduction du type depuis le mimeType
        $this->type = $this->resolveTypeFromMime($mimeType);
        return $this;
    }

    public function getSize(): ?int { return $this->size; }
    public function setSize(?int $size): static { $this->size = $size; return $this; }

    public function getUploadedAt(): ?\DateTimeImmutable { return $this->uploadedAt; }
    public function setUploadedAt(?\DateTimeImmutable $uploadedAt): static { $this->uploadedAt = $uploadedAt; return $this; }

    public function getType(): ?string { return $this->type; }
    public function setType(?string $type): static { $this->type = $type; return $this; }

    public function getArticle(): ?Article { return $this->article; }
    public function setArticle(?Article $article): static { $this->article = $article; return $this; }

    public function getProject(): ?Project { return $this->project; }
    public function setProject(?Project $project): static { $this->project = $project; return $this; }

    public function getTestimonial(): ?Testimonial { return $this->testimonial; }
    public function setTestimonial(?Testimonial $testimonial): static { $this->testimonial = $testimonial; return $this; }

    // ── Méthode privée ────────────────────────────────────────────────────────

    /**
     * Déduit le type métier depuis le mimeType.
     */
    private function resolveTypeFromMime(?string $mimeType): ?string
    {
        if (!$mimeType) return null;

        return match (true) {
            str_starts_with($mimeType, 'image/')       => 'image',
            str_starts_with($mimeType, 'video/')       => 'video',
            str_starts_with($mimeType, 'audio/')       => 'audio',
            in_array($mimeType, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
            ])                                         => 'document',
            default                                    => null,
        };
    }
}