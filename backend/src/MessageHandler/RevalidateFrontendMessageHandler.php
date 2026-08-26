<?php

namespace App\MessageHandler;

use App\Message\RevalidateFrontendMessage;
use App\Service\FrontendRevalidator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RevalidateFrontendMessageHandler
{
    public function __construct(
        private readonly FrontendRevalidator $revalidator,
    ) {
    }

    public function __invoke(RevalidateFrontendMessage $message): void
    {
        $this->revalidator->revalidate($message->tag);
    }
}
