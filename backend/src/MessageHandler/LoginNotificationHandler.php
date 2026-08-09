<?php

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\LoginNotification;
use App\Service\DeviceParser;
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
        private DeviceParser           $deviceParser,
        private EntityManagerInterface $entityManager,
        private LoggerInterface        $logger,
    ) {}

    public function __invoke(LoginNotification $message): void
    {
        $location = $this->resolveLocation($message->ip);
        $device = $this->deviceParser->parse($message->device);

        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->find($message->userId);

        if (!$user instanceof User) {
            $this->logger->warning('LoginNotificationHandler : utilisateur introuvable', [
                'userId' => $message->userId,
            ]);
            return;
        }

        $user->setLastLocation($location['label']);
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
                'device'   => $device,
            ]
        );
    }

    /**
     * @return array{label: string, city: ?string, country_name: ?string, latitude: ?float, longitude: ?float}
     */
    private function resolveLocation(string $ip): array
    {
        $empty = ['city' => null, 'country_name' => null, 'latitude' => null, 'longitude' => null];

        if (!$this->geolocationService->isPublicIp($ip)) {
            return [...$empty, 'label' => 'Réseau local'];
        }

        $location = $this->geolocationService->getLocationFromIp($ip);

        return [...$empty, ...($location ?? []), 'label' => GeolocationService::formatLabel($location) ?? 'Localisation inconnue'];
    }
}