<?php

namespace App\MessageHandler;

use App\Message\OffsiteBackupMessage;
use App\Service\OffsiteBackupUploader;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class OffsiteBackupMessageHandler
{
    public function __construct(
        private readonly OffsiteBackupUploader $uploader,
    ) {
    }

    public function __invoke(OffsiteBackupMessage $message): void
    {
        $this->uploader->upload($message->relativePath);
    }
}
