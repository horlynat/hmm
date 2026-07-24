<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Notifie les administrateurs par e-mail lors d'une erreur grave (5xx, ou
 * AppException::shouldNotify() explicitement true).
 *
 * - Utilise EmailManager::sendNow() (SMTP synchrone) plutôt que sendAsync() :
 *   sendAsync() passe par Messenger, dont le transport est backé par la même
 *   base de données que le reste de l'app — si l'erreur à notifier est
 *   justement une panne DB, la voie async échouerait dans le même domaine de
 *   panne qu'elle est censée signaler. sendNow()/SMTP est un domaine de
 *   panne indépendant.
 * - Destinataires : Notifier::getAdminRecipients() (config/packages/notifier.yaml),
 *   déjà utilisé par AdminAlertNotifier.
 * - Anti-spam : un seul e-mail par heure et par classe d'exception, via le
 *   limiter "error_notification" (config/packages/framework.yaml).
 *
 * Comme AdminAlertNotifier, ne doit jamais faire échouer l'appelant : toute
 * erreur ici est journalisée et avalée.
 */
final class ErrorNotifier
{
    public function __construct(
        private readonly EmailManager $emailManager,
        #[Autowire(service: 'notifier')] private readonly Notifier $notifier,
        #[Autowire(service: 'limiter.error_notification')] private readonly RateLimiterFactory $errorNotificationLimiter,
        #[Autowire(service: 'monolog.logger.app_errors')] private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function notify(\Throwable $throwable, int $statusCode, array $context = []): void
    {
        try {
            $limiter = $this->errorNotificationLimiter->create($throwable::class);
            if (!$limiter->consume(1)->isAccepted()) {
                // Une notification pour ce type d'erreur est déjà partie dans la dernière heure.
                return;
            }

            foreach ($this->notifier->getAdminRecipients() as $recipient) {
                if (!$recipient instanceof EmailRecipientInterface) {
                    continue;
                }

                $this->emailManager->sendNow(
                    to: $recipient->getEmail(),
                    subject: sprintf('[ALERTE] Erreur %d — %s', $statusCode, $throwable::class),
                    template: 'error_alert',
                    context: [
                        'exceptionClass' => $throwable::class,
                        'message' => $throwable->getMessage(),
                        'statusCode' => $statusCode,
                        'context' => $context,
                        'date' => new \DateTimeImmutable(),
                    ],
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error('ErrorNotifier : notification échouée.', ['error' => $e->getMessage()]);
        }
    }
}
