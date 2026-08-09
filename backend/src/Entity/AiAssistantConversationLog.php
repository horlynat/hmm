<?php

namespace App\Entity;

use App\Repository\AiAssistantConversationLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Trace anonymisée d'un échange avec l'assistant IA — jamais le texte brut de
 * la question/réponse, jamais l'IP en clair. Objectif : suivi de coût/abus et
 * amélioration du RAG (quels chunks sont réellement utiles), sans jamais
 * pouvoir reconstituer une conversation (garde-fou RGPD documenté dans
 * main/infra/README.md). Purgée après 90 jours par
 * App\Command\AiAssistantPurgeLogsCommand.
 *
 * Table séparée du contenu métier à dessein (cf. §4.4 du document
 * d'architecture) — aucune colonne ne peut structurellement contenir de texte
 * libre identifiable.
 */
#[ORM\Entity(repositoryClass: AiAssistantConversationLogRepository::class)]
class AiAssistantConversationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_admin'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['api_admin'])]
    private \DateTimeImmutable $createdAt;

    /** sha256(IP + APP_SECRET) — jamais l'IP en clair. */
    #[ORM\Column(length: 64)]
    private string $ipHash = '';

    #[ORM\Column(length: 5)]
    private string $locale = 'fr';

    #[ORM\Column]
    private int $questionLength = 0;

    #[ORM\Column]
    private int $answerLength = 0;

    /** @var int[] ids des AiAssistantDocumentChunk effectivement injectés dans le contexte. */
    #[ORM\Column(type: Types::JSON)]
    private array $chunkIdsUsed = [];

    /** "sonnet" ou "haiku" — nom du modèle Claude effectivement utilisé (après repli éventuel). */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(nullable: true)]
    private ?int $geminiTokens = null;

    #[ORM\Column(nullable: true)]
    private ?int $claudeTokens = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6, nullable: true)]
    private ?string $costUsd = null;

    #[ORM\Column(nullable: true)]
    private ?int $latencyMs = null;

    #[ORM\Column]
    private bool $blocked = false;

    /** "input_injection_suspected" | "output_leak_suspected" | "budget_exceeded" | "feature_disabled" | "upstream_error" | null */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $blockReason = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getIpHash(): string
    {
        return $this->ipHash;
    }

    public function setIpHash(string $ipHash): static
    {
        $this->ipHash = $ipHash;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getQuestionLength(): int
    {
        return $this->questionLength;
    }

    public function setQuestionLength(int $questionLength): static
    {
        $this->questionLength = $questionLength;

        return $this;
    }

    public function getAnswerLength(): int
    {
        return $this->answerLength;
    }

    public function setAnswerLength(int $answerLength): static
    {
        $this->answerLength = $answerLength;

        return $this;
    }

    /** @return int[] */
    public function getChunkIdsUsed(): array
    {
        return $this->chunkIdsUsed;
    }

    /** @param int[] $chunkIdsUsed */
    public function setChunkIdsUsed(array $chunkIdsUsed): static
    {
        $this->chunkIdsUsed = $chunkIdsUsed;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getGeminiTokens(): ?int
    {
        return $this->geminiTokens;
    }

    public function setGeminiTokens(?int $geminiTokens): static
    {
        $this->geminiTokens = $geminiTokens;

        return $this;
    }

    public function getClaudeTokens(): ?int
    {
        return $this->claudeTokens;
    }

    public function setClaudeTokens(?int $claudeTokens): static
    {
        $this->claudeTokens = $claudeTokens;

        return $this;
    }

    public function getCostUsd(): ?string
    {
        return $this->costUsd;
    }

    public function setCostUsd(?string $costUsd): static
    {
        $this->costUsd = $costUsd;

        return $this;
    }

    public function getLatencyMs(): ?int
    {
        return $this->latencyMs;
    }

    public function setLatencyMs(?int $latencyMs): static
    {
        $this->latencyMs = $latencyMs;

        return $this;
    }

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    public function setBlocked(bool $blocked): static
    {
        $this->blocked = $blocked;

        return $this;
    }

    public function getBlockReason(): ?string
    {
        return $this->blockReason;
    }

    public function setBlockReason(?string $blockReason): static
    {
        $this->blockReason = $blockReason;

        return $this;
    }
}
