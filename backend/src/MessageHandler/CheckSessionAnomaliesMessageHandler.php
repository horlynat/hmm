<?php

namespace App\MessageHandler;

use App\Entity\User;
use App\Enum\NotificationPriorityEnum;
use App\Message\CheckSessionAnomaliesMessage;
use App\Repository\UserSessionRepository;
use App\Service\AdminAlertNotifier;
use App\Service\GeolocationService;
use App\Service\LiveSessionStateResolver;
use App\Service\SessionAnomalyDetector;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Recherche un "voyage impossible" (cf. SessionAnomalyDetector) parmi les
 * sessions actuellement ACTIVES d'un utilisateur, juste après une nouvelle
 * connexion. Purement une alerte pour un humain — ne révoque jamais de
 * session automatiquement ici : un VPN ou un faux positif de géolocalisation
 * IP ne doit jamais éjecter un utilisateur légitime tout seul.
 */
#[AsMessageHandler]
class CheckSessionAnomaliesMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserSessionRepository $userSessionRepository,
        private LiveSessionStateResolver $liveSessionStateResolver,
        private GeolocationService $geolocationService,
        private SessionAnomalyDetector $anomalyDetector,
        private AdminAlertNotifier $adminAlertNotifier,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CheckSessionAnomaliesMessage $message): void
    {
        $user = $this->entityManager->getRepository(User::class)->find($message->userId);
        if (!$user instanceof User) {
            return;
        }

        $liveSessions = $this->liveSessionStateResolver->resolveAll();

        $activeSessions = array_values(array_filter(
            $this->userSessionRepository->findByUserOrderedByCreatedAt($user),
            static fn ($s) => ($liveSessions[$s->getSessionId()]['active'] ?? false),
        ));

        if (count($activeSessions) < 2) {
            return;
        }

        $withCoordinates = [];
        foreach ($activeSessions as $session) {
            $location = $this->geolocationService->getLocationFromIp($session->getIp());
            if (null === $location || null === $location['latitude'] || null === $location['longitude']) {
                continue;
            }

            $withCoordinates[] = [
                'sessionId' => $session->getSessionId(),
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'at' => (new \DateTimeImmutable())->setTimestamp($liveSessions[$session->getSessionId()]['lastActivityAt']),
            ];
        }

        $anomalies = $this->anomalyDetector->detectImpossibleTravel($withCoordinates);
        if ([] === $anomalies) {
            return;
        }

        $this->logger->warning('Voyage impossible détecté entre sessions actives', [
            'userId' => $user->getId(),
            'anomalies' => $anomalies,
        ]);

        foreach ($anomalies as $anomaly) {
            $this->adminAlertNotifier->alert(
                NotificationPriorityEnum::URGENT,
                'Voyage impossible détecté',
                sprintf(
                    "Deux sessions actives de %s sont distantes de %s km, un déplacement impliquant ~%s km/h — physiquement impossible. Vérifiez le compte (identifiants potentiellement partagés ou compromis).",
                    $user->getEmail(),
                    number_format($anomaly['distanceKm'], 0, ',', ' '),
                    number_format($anomaly['impliedSpeedKmh'], 0, ',', ' '),
                ),
            );
        }
    }
}
