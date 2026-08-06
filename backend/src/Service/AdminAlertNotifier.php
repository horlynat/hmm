<?php

namespace App\Service;

use App\Enum\NotificationPriorityEnum;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notifier;

/**
 * Point d'entrée unique pour les alertes destinées aux administrateurs.
 *
 * Le canal réellement utilisé (e-mail, push) dépend de NotificationPreference
 * (Paramètres > Notifications), via le service "notifier.channel_policy"
 * (App\Notifier\DatabaseChannelPolicy). Les destinataires sont ceux déclarés
 * dans config/packages/notifier.yaml (admin_recipients).
 *
 * Ne doit jamais faire échouer l'action métier qui déclenche l'alerte : une
 * panne du transport (SMTP down, ntfy injoignable...) est journalisée mais
 * avalée plutôt que propagée (ex: la création d'un ContactMessage ne doit pas
 * échouer parce que l'e-mail d'alerte n'a pas pu partir).
 */
final class AdminAlertNotifier
{
    public function __construct(
        #[Autowire(service: 'notifier')] private readonly Notifier $notifier,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function alert(NotificationPriorityEnum $priority, string $subject, string $content): void
    {
        $notification = (new Notification($subject))
            ->content($content)
            ->importance($priority->value);

        try {
            $this->notifier->send($notification, ...$this->notifier->getAdminRecipients());
        } catch (\Throwable $e) {
            $this->logger->error('AdminAlertNotifier : envoi de l\'alerte échoué.', [
                'priority' => $priority->value,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
