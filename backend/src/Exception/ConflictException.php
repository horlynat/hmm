<?php

namespace App\Exception;

/**
 * Conflit métier : la ressource existe déjà (ex : contrainte d'unicité
 * violée lors d'un flush concurrent, après passage réussi de la validation
 * applicative — cf. CollaboratorRegistrationProcessor). HTTP 409.
 */
final class ConflictException extends AppException
{
    public function getHttpStatusCode(): int
    {
        return 409;
    }

    public function getTitle(): string
    {
        return 'Conflit de ressource';
    }
}
