<?php

namespace App\EventSubscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ConnectionException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * FrankenPHP tourne en mode worker (frankenphp/Caddyfile) : le process PHP,
 * et donc la connexion Doctrine qu'il contient, reste vivant en mémoire
 * entre les requêtes au lieu d'être recréé à chaque requête comme en
 * PHP-FPM classique.
 *
 * Après une période d'inactivité (creux de trafic), MySQL ferme côté
 * serveur la connexion TCP restée inactive (wait_timeout/interactive_timeout,
 * erreurs MySQL 2006/4031). Doctrine ne le détecte pas tant qu'aucune
 * requête SQL n'est tentée : la première requête métier du worker qui suit
 * ce creux casse alors avec une PDOException — c'est cette classe
 * d'incident que ErrorNotifier remonte par e-mail ("[ALERTE] Erreur 500").
 *
 * En sondant la connexion avant toute logique métier et en la fermant si
 * elle est morte, on force Doctrine à en rouvrir une neuve (connexion
 * paresseuse, cf. Connection::connect()) au tout premier accès réel de la
 * requête — invisible pour l'utilisateur, sans renoncer au gain de perf du
 * mode worker.
 *
 * Priorité kernel.request très haute : doit s'exécuter avant le Firewall de
 * sécurité (qui recharge le User depuis la base dès kernel.request) et tout
 * autre listener susceptible de toucher la DB.
 */
final class DoctrineConnectionPingSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire(service: 'monolog.logger.app_errors')] private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 2048],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->connection->isConnected()) {
            // Rien à sonder : soit une sous-requête, soit un worker qui n'a
            // encore jamais ouvert de connexion sur ce cycle de vie — elle
            // s'ouvrira saine au premier accès normal.
            return;
        }

        try {
            $this->connection->fetchOne('SELECT 1');
        } catch (ConnectionException $e) {
            $this->logger->warning('Connexion DB morte détectée en début de requête (worker FrankenPHP) — fermeture pour forcer une reconnexion transparente.', [
                'error' => $e->getMessage(),
            ]);
            $this->connection->close();
        }
    }
}
