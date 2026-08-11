<?php

namespace App\MessageHandler;

use App\Message\AiAssistantIngestMessage;
use App\Service\AiAssistantIngestionService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AiAssistantIngestMessageHandler
{
    public function __construct(private readonly AiAssistantIngestionService $ingestionService)
    {
    }

    public function __invoke(AiAssistantIngestMessage $message): void
    {
        $this->ingestionService->ingest($message->entityType, $message->entityId);
    }
}
