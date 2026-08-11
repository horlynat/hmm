<?php

namespace App\Exception;

/**
 * L'assistant IA conversationnel n'a pas pu répondre : feature flag
 * désactivé, plafond budgétaire mensuel dépassé, ou Claude/Gemini injoignable.
 * HTTP 503 — le widget frontend doit basculer sur un message de repli plutôt
 * que d'afficher une erreur brute.
 */
final class AiAssistantUnavailableException extends AppException
{
    public function getHttpStatusCode(): int
    {
        return 503;
    }

    public function getTitle(): string
    {
        return 'Assistant IA indisponible';
    }
}
