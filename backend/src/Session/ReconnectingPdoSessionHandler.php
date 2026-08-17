<?php

namespace App\Session;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

/**
 * Enveloppe PdoSessionHandler pour couvrir un trou laissé par
 * DoctrineConnectionPingSubscriber (cf. ce fichier pour le contexte complet
 * FrankenPHP worker / connexion inactive fermée par MySQL).
 *
 * config/services.yaml partageait jusqu'ici le PDO natif de Doctrine avec
 * PdoSessionHandler via un factory Symfony ('app.pdo_native_connection' ->
 * Connection::getNativeConnection()) — mais un factory Symfony n'est appelé
 * qu'UNE SEULE FOIS par service, et son résultat est ensuite mis en cache
 * comme n'importe quel service. Connection::close() (ce que fait le ping
 * subscriber sur une connexion morte) vide juste $this->_conn côté Doctrine
 * ; Doctrine se reconnecte alors tout seul, de façon transparente, dès sa
 * prochaine requête. Mais le PDO déjà distribué à PdoSessionHandler ne
 * "suit" pas ce remplacement : c'est un objet figé, pas une référence
 * vivante. Dès la première reconnexion Doctrine dans la vie d'un worker,
 * PdoSessionHandler continue de travailler sur un PDO orphelin et mort —
 * chaque lecture/écriture de session échoue avec la même PDOException 2006,
 * jusqu'au recyclage complet du worker. Constaté en prod : plusieurs 500 sur
 * /login (PdoSessionHandler.php:770) malgré le correctif Doctrine déjà en
 * place et déployé.
 *
 * Fix : ne jamais figer le PDO plus d'un appel. Chaque opération de session
 * qui échoue avec 2006/4031 (mêmes codes que
 * Doctrine\DBAL\Driver\API\MySQL\ExceptionConverter::convert() pour
 * ConnectionLost) force Doctrine à fermer/rouvrir sa connexion, reconstruit
 * un PdoSessionHandler interne à partir du PDO frais qui en résulte, et
 * rejoue l'opération une fois. Lire/écrire/détruire une session est sans
 * risque à rejouer (contrairement à une requête métier arbitraire, d'où la
 * prudence "jamais de retry auto" du ping subscriber) : c'est pour ça qu'on
 * peut se permettre ici un vrai retry, pas juste un sondage préventif.
 */
final class ReconnectingPdoSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    private const LOST_CONNECTION_CODES = [2006, 4031];

    private PdoSessionHandler $inner;

    public function __construct(
        private readonly Connection $connection,
        #[Autowire(service: 'monolog.logger.app_errors')] private readonly LoggerInterface $logger,
    ) {
        $this->inner = $this->buildHandler();
    }

    public function open(string $path, string $name): bool
    {
        return (bool) $this->retry(fn (): bool => $this->inner->open($path, $name));
    }

    public function close(): bool
    {
        return (bool) $this->retry(fn (): bool => $this->inner->close());
    }

    public function read(string $id): string
    {
        return (string) $this->retry(fn (): string => $this->inner->read($id));
    }

    public function write(string $id, string $data): bool
    {
        return (bool) $this->retry(fn (): bool => $this->inner->write($id, $data));
    }

    public function destroy(string $id): bool
    {
        return (bool) $this->retry(fn (): bool => $this->inner->destroy($id));
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->retry(fn (): int|false => $this->inner->gc($max_lifetime));
    }

    public function validateId(string $id): bool
    {
        return (bool) $this->retry(fn (): bool => $this->inner->validateId($id));
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return (bool) $this->retry(fn (): bool => $this->inner->updateTimestamp($id, $data));
    }

    private function retry(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (\PDOException $e) {
            if (!self::isConnectionLost($e)) {
                throw $e;
            }

            $this->logger->warning('Connexion PDO de session morte détectée — reconnexion et nouvelle tentative.', [
                'error' => $e->getMessage(),
            ]);

            // Force Doctrine à rouvrir une connexion saine (connect() est
            // lazy : le prochain appel la recrée) avant d'en reprendre le
            // PDO natif frais pour reconstruire le handler interne.
            $this->connection->close();
            $this->inner = $this->buildHandler();

            return $operation();
        }
    }

    private static function isConnectionLost(\PDOException $e): bool
    {
        return \in_array($e->errorInfo[1] ?? null, self::LOST_CONNECTION_CODES, true);
    }

    private function buildHandler(): PdoSessionHandler
    {
        return new PdoSessionHandler($this->connection->getNativeConnection(), [
            // Même raison qu'auparavant dans services.yaml : la connexion PDO
            // est partagée avec l'ORM, LOCK_TRANSACTIONAL (défaut) ouvrirait
            // une transaction PDO en conflit avec celles de Doctrine.
            'lock_mode' => PdoSessionHandler::LOCK_ADVISORY,
        ]);
    }
}
