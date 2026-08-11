<?php

namespace App\Message;

/**
 * Peuple LoginHistory::location de manière asynchrone (appel externe
 * ipapi.co) après coup, pour ne pas ralentir la réponse de connexion elle-même.
 */
class EnrichLoginLocationMessage
{
    public function __construct(
        public readonly int $loginHistoryId,
    ) {
    }
}
