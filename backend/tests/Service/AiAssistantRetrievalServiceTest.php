<?php

namespace App\Tests\Service;

use App\Entity\AiAssistantDocumentChunk;
use App\Repository\AiAssistantDocumentChunkRepository;
use App\Service\AiAssistantRetrievalService;
use PHPUnit\Framework\TestCase;

final class AiAssistantRetrievalServiceTest extends TestCase
{
    /** @param float[] $embedding */
    private function chunk(array $embedding, string $label): AiAssistantDocumentChunk
    {
        return (new AiAssistantDocumentChunk())
            ->setEntityType('Project')
            ->setEntityId(1)
            ->setEmbedding($embedding)
            ->setChunkSummary($label)
            ->setMetadata(['is_public' => true, 'label' => $label]);
    }

    public function testReturnsMostSimilarChunkFirst(): void
    {
        $exact = $this->chunk([1.0, 0.0, 0.0], 'exact');
        $orthogonal = $this->chunk([0.0, 1.0, 0.0], 'orthogonal');
        $opposite = $this->chunk([-1.0, 0.0, 0.0], 'opposite');

        $repository = $this->createStub(AiAssistantDocumentChunkRepository::class);
        $repository->method('findAllPublic')->willReturn([$orthogonal, $opposite, $exact]);

        $results = (new AiAssistantRetrievalService($repository))->findRelevantChunks([1.0, 0.0, 0.0], topK: 2);

        self::assertCount(2, $results);
        self::assertSame('exact', $results[0]->getChunkSummary());
        self::assertSame('orthogonal', $results[1]->getChunkSummary());
    }

    public function testReturnsEmptyArrayWhenNoChunksExist(): void
    {
        $repository = $this->createStub(AiAssistantDocumentChunkRepository::class);
        $repository->method('findAllPublic')->willReturn([]);

        self::assertSame([], (new AiAssistantRetrievalService($repository))->findRelevantChunks([1.0, 0.0]));
    }
}
