<?php

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\LoginNotification;
use App\Service\EmailManager;
use App\Service\GeolocationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class LoginNotificationHandler
{
    public function __construct(
        private EmailManager           $emailManager,
        private GeolocationService     $geolocationService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface        $logger,
    ) {}

    public function __invoke(LoginNotification $message): void
    {
        $location = $this->resolveLocation($message->ip);

        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->find($message->userId);

        if (!$user instanceof User) {
            $this->logger->warning('LoginNotificationHandler : utilisateur introuvable', [
                'userId' => $message->userId,
            ]);
            return;
        }

        $user->setLastLocation($location);
        $this->entityManager->flush();

        $this->emailManager->sendAsync(
            to:       $message->email,
            subject:  'Sécurité : Nouvelle connexion détectée',
            template: 'login_alert',
            context:  [
                'fullName' => $message->fullName,
                'date'     => $message->date,
                'ip'       => $message->ip,
                'location' => $location,
                'device'   => $message->device,
            ]
        );
    }

    private function resolveLocation(string $ip): string
    {
        if (!$this->geolocationService->isPublicIp($ip)) {
            return 'Réseau local';
        }

        $label = GeolocationService::formatLabel($this->geolocationService->getLocationFromIp($ip));

        return $label ?? 'Localisation inconnue';
    }
}