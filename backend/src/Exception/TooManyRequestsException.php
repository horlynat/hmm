<?php

namespace App\Exception;

/**
 * Limite de soumissions atteinte pour un formulaire public (contact, devis,
 * témoignage, inscription). HTTP 429.
 */
final class TooManyRequestsException extends AppException
{
    public function getHttpStatusCode(): int
    {
        return 429;
    }

    public function getTitle(): string
    {
        return 'Trop de tentatives';
    }
}
