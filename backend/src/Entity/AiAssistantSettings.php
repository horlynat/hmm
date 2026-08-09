<?php

namespace App\Entity;

use App\Repository\AiAssistantSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Réglages bilingues du widget assistant IA (message d'accueil, réponse par
 * défaut) — séparés des entrées de FAQ (AiAssistantEntry) car ce sont deux
 * axes distincts : un texte fixe vs. une liste de questions/réponses.
 *
 * Table à ligne unique : AiAssistantSettingsRepository::getSettings()
 * garantit qu'une seule instance existe (créée à la demande, cf. SystemSetting).
 */
#[ORM\Entity(repositoryClass: AiAssistantSettingsRepository::class)]
class AiAssistantSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_public', 'api_admin'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Le message d'accueil est obligatoire.")]
    #[Groups(['api_public', 'api_admin'])]
    private string $greeting = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $greetingEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "La réponse par défaut est obligatoire.")]
    #[Groups(['api_public', 'api_admin'])]
    private string $fallback = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $fallbackEn = null;

    /**
     * Coupe-circuit du chat conversationnel (texte libre → Gemini+Claude), à
     * ne pas confondre avec les chips de FAQ qui restent toujours actives
     * (réponses locales, sans appel externe). Décoché ici, l'appel à
     * /api/assistant/chat échoue immédiatement en 503 — permet de désactiver
     * l'assistant en un clic depuis l'admin, sans redéploiement (cf. §4.7 du
     * document d'architecture assistant IA).
     */
    #[ORM\Column]
    #[Groups(['api_public', 'api_admin'])]
    private bool $aiAssistantEnabled = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGreeting(): string
    {
        return $this->greeting;
    }

    public function setGreeting(string $greeting): static
    {
        $this->greeting = $greeting;

        return $this;
    }

    public function getGreetingEn(): ?string
    {
        return $this->greetingEn;
    }

    public function setGreetingEn(?string $greetingEn): static
    {
        $this->greetingEn = $greetingEn;

        return $this;
    }

    public function getFallback(): string
    {
        return $this->fallback;
    }

    public function setFallback(string $fallback): static
    {
        $this->fallback = $fallback;

        return $this;
    }

    public function getFallbackEn(): ?string
    {
        return $this->fallbackEn;
    }

    public function setFallbackEn(?string $fallbackEn): static
    {
        $this->fallbackEn = $fallbackEn;

        return $this;
    }

    public function isAiAssistantEnabled(): bool
    {
        return $this->aiAssistantEnabled;
    }

    public function setAiAssistantEnabled(bool $aiAssistantEnabled): static
    {
        $this->aiAssistantEnabled = $aiAssistantEnabled;

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
