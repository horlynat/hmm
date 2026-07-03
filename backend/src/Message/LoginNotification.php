<?php

namespace App\Message;

class LoginNotification
{
    public function __construct(
        public readonly int                $userId,
        public readonly string             $email,
        public readonly ?string             $fullName,
        public readonly string             $ip,
        public readonly string             $device,
        public readonly \DateTimeImmutable $date,
    ) {}
}