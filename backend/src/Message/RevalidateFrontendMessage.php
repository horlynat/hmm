<?php

namespace App\Message;

/**
 * Demande de revalidation d'un tag de cache ISR côté frontend (cf.
 * App\Service\FrontendRevalidator) — traitée de façon asynchrone par
 * App\MessageHandler\RevalidateFrontendMessageHandler, pour ne pas ralentir
 * la réponse de la sauvegarde admin qui l'a déclenchée.
 */
class RevalidateFrontendMessage
{
    public function __construct(
        public readonly string $tag,
    ) {
    }
}
