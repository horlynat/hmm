<?php

namespace App\Message;

/**
 * Demande de (ré)ingestion d'une entité de contenu (Project/Article/Experience)
 * dans le pipeline RAG de l'assistant IA — traité de façon asynchrone par
 * App\MessageHandler\AiAssistantIngestMessageHandler (transport "async" déjà
 * en place, cf. config/packages/messenger.yaml).
 */
class AiAssistantIngestMessage
{
    public function __construct(
        public readonly string $entityType,
        public readonly int $entityId,
    ) {
    }
}
