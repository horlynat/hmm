<?php

namespace App\Message;

class SendEmail
{
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $template,
        public readonly array  $context = [],
    ) {}
}