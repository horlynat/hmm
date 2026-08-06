<?php

namespace App\Message;

class SendEmail
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $template,
        public readonly array  $context = [],
    ) {}
}