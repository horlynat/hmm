<?php

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\LoginNotification;
use App\Service\EmailManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
class LoginNotificationHandler
{
    public function __construct(
        private EmailManager           $emailManager,
        private HttpClientInterface    $httpClient,
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
        if ($this->isLocalIp($ip)) {
            return 'Réseau local';
        }

        try {
            $response = $this->httpClient->request('GET', "https://ip-api.com/json/{$ip}", [
                'timeout' => 3,
            ]);
            $data = $response->toArray(false);

            if (($data['status'] ?? '') === 'success') {
                return trim(($data['city'] ?? 'Inconnu') . ', ' . ($data['country'] ?? 'Inconnu'));
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Géolocalisation échouée : ' . $e->getMessage(), ['ip' => $ip]);
        }

        return 'Localisation inconnue';
    }

    private function isLocalIp(string $ip): bool
    {
        return in_array($ip, ['127.0.0.1', '::1'], true)
            || str_starts_with($ip, '192.168.')
            || str_starts_with($ip, '10.')
            || str_starts_with($ip, '172.');
    }
}