<?php

namespace App\Entity;

use App\Repository\AiAssistantDocumentChunkRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un fragment de contenu du portfolio (Project/Article/Experience), résumé et
 * vectorisé par Gemini lors de l'ingestion asynchrone, utilisé comme contexte
 * RAG par l'assistant conversationnel (App\Service\AiAssistantRetrievalService).
 *
 * Pas de pgvector (la prod tourne en MySQL, pas Postgres) : `embedding` est un
 * simple JSON (tableau de floats), la similarité cosinus est calculée en PHP
 * au moment du retrieval — largement suffisant au volume de contenu d'un
 * portfolio (quelques dizaines de lignes).
 *
 * `metadata.is_public` est revérifié systématiquement par le retrieval, en
 * défense en profondeur, même si seules des entités déjà publiques
 * (Project/Article/Experience exposées par l'API publique) alimentent
 * aujourd'hui l'ingestion.
 */
#[ORM\Entity(repositoryClass: AiAssistantDocumentChunkRepository::class)]
#[ORM\Table(name: 'document_embedding')]
#[ORM\UniqueConstraint(name: 'uniq_entity_chunk', columns: ['entity_type', 'entity_id', 'chunk_index'])]
class AiAssistantDocumentChunk
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Nom court de la classe source : "Project", "Article" ou "Experience". */
    #[ORM\Column(length: 50)]
    private string $entityType = '';

    #[ORM\Column]
    private int $entityId = 0;

    /** Position du fragment si le contenu source a dû être découpé (0 par défaut, un seul chunk). */
    #[ORM\Column]
    private int $chunkIndex = 0;

    #[ORM\Column(type: Types::TEXT)]
    private string $chunkText = '';

    /** Résumé généré par Gemini — c'est ce texte qui est injecté dans le contexte transmis à Claude. */
    #[ORM\Column(type: Types::TEXT)]
    private string $chunkSummary = '';

    /** @var float[] */
    #[ORM\Column(type: Types::JSON)]
    private array $embedding = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getChunkIndex(): int
    {
        return $this->chunkIndex;
    }

    public function setChunkIndex(int $chunkIndex): static
    {
        $this->chunkIndex = $chunkIndex;

        return $this;
    }

    public function getChunkText(): string
    {
        return $this->chunkText;
    }

    public function setChunkText(string $chunkText): static
    {
        $this->chunkText = $chunkText;

        return $this;
    }

    public function getChunkSummary(): string
    {
        return $this->chunkSummary;
    }

    public function setChunkSummary(string $chunkSummary): static
    {
        $this->chunkSummary = $chunkSummary;

        return $this;
    }

    /** @return float[] */
    public function getEmbedding(): array
    {
        return $this->embedding;
    }

    /** @param float[] $embedding */
    public function setEmbedding(array $embedding): static
    {
        $this->embedding = $embedding;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /** @param array<string, mixed> $metadata */
    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function isPublic(): bool
    {
        return true === ($this->metadata['is_public'] ?? false);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
