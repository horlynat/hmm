<?php

namespace App\Entity;

use App\Enum\IntegrationTypeEnum;
use App\Repository\IntegrationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Intégration externe (Slack, GitHub, CRM, API générique).
 *
 * Le secret (apiKeyEncrypted) n'est jamais stocké en clair : il transite par
 * App\Service\SecretEncryptor avant persistance (voir AdminIntegrationController).
 */
#[ORM\Entity(repositoryClass: IntegrationRepository::class)]
class Integration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: IntegrationTypeEnum::class)]
    private IntegrationTypeEnum $type;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le nom de l'intégration est obligatoire.")]
    private string $name = '';

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url(message: "L'URL du webhook n'est pas valide.")]
    private ?string $webhookUrl = null;

    /** Secret chiffré (clé API / token), jamais exposé en clair après sauvegarde. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $apiKeyEncrypted = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $config = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastTestedAt = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $lastTestSuccess = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(IntegrationTypeEnum $type)
    {
        $this->type = $type;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): IntegrationTypeEnum
    {
        return $this->type;
    }

    public function setType(IntegrationTypeEnum $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getWebhookUrl(): ?string
    {
        return $this->webhookUrl;
    }

    public function setWebhookUrl(?string $webhookUrl): static
    {
        $this->webhookUrl = $webhookUrl;

        return $this;
    }

    public function getApiKeyEncrypted(): ?string
    {
        return $this->apiKeyEncrypted;
    }

    public function setApiKeyEncrypted(?string $apiKeyEncrypted): static
    {
        $this->apiKeyEncrypted = $apiKeyEncrypted;

        return $this;
    }

    public function hasApiKey(): bool
    {
        return null !== $this->apiKeyEncrypted && '' !== $this->apiKeyEncrypted;
    }

    /** @return array<string, mixed>|null */
    public function getConfig(): ?array
    {
        return $this->config;
    }

    /** @param array<string, mixed>|null $config */
    public function setConfig(?array $config): static
    {
        $this->config = $config;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getLastTestedAt(): ?\DateTimeImmutable
    {
        return $this->lastTestedAt;
    }

    public function getLastTestSuccess(): ?bool
    {
        return $this->lastTestSuccess;
    }

    public function recordTestResult(bool $success): static
    {
        $this->lastTestedAt = new \DateTimeImmutable();
        $this->lastTestSuccess = $success;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
}
