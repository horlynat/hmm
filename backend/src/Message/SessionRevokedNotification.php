<?php

namespace App\Message;

/**
 * Prévient un utilisateur qu'un administrateur a mis fin de force à l'une de
 * ses sessions (cf. AdminSecuritySessionController::revoke()/revokeAll()/
 * revokeForUser()) — jamais dispatché pour l'éviction "douce" par limite de
 * sessions concurrentes (cf. LoginListener::enforceConcurrentSessionLimit()),
 * qui n'a rien d'une action de sécurité à signaler.
 */
class SessionRevokedNotification
{
    public function __construct(
        public readonly int $userId,
        public readonly string $email,
        public readonly string $fullName,
        public readonly ?string $ip,
        public readonly ?string $device,
        public readonly \DateTimeImmutable $date,
        public readonly ?string $revokedByLabel,
    ) {
    }
}
