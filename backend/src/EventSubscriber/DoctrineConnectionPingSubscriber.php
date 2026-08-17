<?php

namespace App\EventSubscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ConnectionException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * FrankenPHP tourne en mode worker (frankenphp/Caddyfile), et le service
 * messenger-worker (docker-compose.prod.yml) consomme la file async pendant
 * jusqu'à 3600s d'affilée (messenger:consume) : dans les deux cas, le
 * process PHP — et donc la connexion Doctrine qu'il contient — reste vivant
 * en mémoire sur la durée, au lieu d'être recréé à chaque unité de travail
 * comme en PHP-FPM classique.
 *
 * Après une période d'inactivité (creux de trafic, ou simplement un
 * incident MySQL passager), MySQL ferme côté serveur la connexion TCP
 * restée inactive (wait_timeout/interactive_timeout, erreurs MySQL
 * 2006/4031). Doctrine ne le détecte pas tant qu'aucune requête SQL n'est
 * tentée : le prochain accès réel casse alors avec une PDOException —
 * c'est cette classe d'incident que ErrorNotifier remonte par e-mail
 * ("[ALERTE] Erreur 500") côté HTTP, et qui côté messenger-worker ferait
 * échouer silencieusement tout message traité par ce worker jusqu'à son
 * recyclage (jusqu'à 1h) sans qu'aucune alerte ne parte (kernel.exception
 * n'existe pas en dehors du HttpKernel — cf. ExceptionSubscriber, jamais
 * déclenché ici).
 *
 * En sondant la connexion avant toute logique métier et en la fermant si
 * elle est morte, on force Doctrine à en rouvrir une neuve (connexion
 * paresseuse, cf. Connection::connect()) au tout premier accès réel qui
 * suit — invisible pour l'utilisateur/le message, sans renoncer au gain de
 * perf du mode worker.
 *
 * - kernel.request, priorité très haute : doit s'exécuter avant le
 *   Firewall de sécurité (qui recharge le User depuis la base dès
 *   kernel.request) et tout autre listener susceptible de toucher la DB.
 * - messenger.worker_running : dispatché par Worker::run() après CHAQUE
 *   itération de boucle (message traité ou non, cf. WorkerRunningEvent) —
 *   pas de "avant réception" possible côté Messenger (le receiver
 *   doctrine:// interroge déjà la DB pour savoir s'il y a un message à
 *   recevoir), donc on soigne la connexion juste après coup, en
 *   préparation de la prochaine itération : un message qui tombe pile sur
 *   une connexion morte peut encore échouer une fois (retry_strategy de
 *   Messenger s'en charge), mais le worker s'auto-guérit avant le suivant
 *   au lieu de rester cassé jusqu'à son recyclage.
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
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            // Rien à sonder pour une sous-requête.
            return;
        }

        $this->pingAndHealIfNeeded();
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        $this->pingAndHealIfNeeded();
    }

    private function pingAndHealIfNeeded(): void
    {
        if (!$this->connection->isConnected()) {
            // Rien à sonder : connexion jamais encore ouverte sur ce cycle
            // de vie — elle s'ouvrira saine au premier accès normal.
            return;
        }

        try {
            $this->connection->fetchOne('SELECT 1');
        } catch (ConnectionException $e) {
            $this->logger->warning('Connexion DB morte détectée — fermeture pour forcer une reconnexion transparente.', [
                'error' => $e->getMessage(),
            ]);
            $this->connection->close();
        }
    }
}
