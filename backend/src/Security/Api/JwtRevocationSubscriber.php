<?php

namespace App\Security\Api;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Révocation des JWT (P1 #8), en complément de la liste de blocage par `jti`
 * de lexik (POST /api/logout, cf. ApiLogoutController) :
 *
 * - à l'émission : claim `tv` = User::$tokenVersion ;
 * - au décodage (chaque requête API authentifiée) : le jeton est refusé
 *   (401 « Invalid JWT Token ») si `tv` ne correspond plus à la version en
 *   base — changement de mot de passe, déconnexion forcée, changement
 *   d'e-mail, suppression du compte (cf. UserSessionRevoker::revokeAll()),
 *   ou si le compte n'existe plus.
 *
 * Un jeton émis avant ce mécanisme (sans `tv`) est traité comme `tv = 0` : il
 * reste valable jusqu'à son expiration naturelle tant que le compte n'a
 * jamais été révoqué.
 */
final class JwtRevocationSubscriber implements EventSubscriberInterface
{
    public const CLAIM = 'tv';

    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_CREATED => 'onJwtCreated',
            Events::JWT_DECODED => 'onJwtDecoded',
        ];
    }

    public function onJwtCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();
        $payload[self::CLAIM] = $user->getTokenVersion();
        $event->setData($payload);
    }

    public function onJwtDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        $identifier = $payload['username'] ?? null;
        if (!\is_string($identifier) || '' === $identifier) {
            $event->markAsInvalid();

            return;
        }

        $current = $this->connection->fetchOne('SELECT token_version FROM `user` WHERE email = :email', ['email' => $identifier]);
        if (false === $current) {
            $event->markAsInvalid();

            return;
        }

        $claimed = $payload[self::CLAIM] ?? 0;
        if (!\is_int($claimed) || $claimed !== (int) $current) {
            $event->markAsInvalid();
        }
    }
}
