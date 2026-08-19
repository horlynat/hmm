<?php

namespace App\MessageHandler;

use App\Message\SessionRevokedNotification;
use App\Service\DeviceParser;
use App\Service\EmailManager;
use App\Service\GeolocationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Même principe que LoginNotificationHandler (géolocalisation IP hors du
 * chemin critique de la requête HTTP qui a déclenché la révocation).
 */
#[AsMessageHandler]
class SessionRevokedNotificationHandler
{
    public function __construct(
        private EmailManager $emailManager,
        private GeolocationService $geolocationService,
        private DeviceParser $deviceParser,
    ) {
    }

    public function __invoke(SessionRevokedNotification $message): void
    {
        $location = null;
        if (null !== $message->ip && $this->geolocationService->isPublicIp($message->ip)) {
            $location = $this->geolocationService->getLocationFromIp($message->ip);
        }

        $this->emailManager->sendAsync(
            to: $message->email,
            subject: 'Sécurité : une de vos sessions a été déconnectée',
            template: 'session_revoked',
            context: [
                'fullName' => $message->fullName,
                'date' => $message->date,
                'ip' => $message->ip,
                'location' => $location,
                'device' => $this->deviceParser->parse($message->device),
                'revokedByLabel' => $message->revokedByLabel,
            ],
        );
    }
}
