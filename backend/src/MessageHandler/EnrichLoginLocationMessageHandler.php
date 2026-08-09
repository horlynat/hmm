<?php

namespace App\MessageHandler;

use App\Entity\LoginHistory;
use App\Message\EnrichLoginLocationMessage;
use App\Service\GeolocationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class EnrichLoginLocationMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GeolocationService     $geolocationService,
        private LoggerInterface        $logger,
    ) {
    }

    public function __invoke(EnrichLoginLocationMessage $message): void
    {
        $loginHistory = $this->entityManager->getRepository(LoginHistory::class)->find($message->loginHistoryId);

        if (!$loginHistory instanceof LoginHistory || null === $loginHistory->getIp()) {
            return;
        }

        $label = GeolocationService::formatLabel(
            $this->geolocationService->getLocationFromIp($loginHistory->getIp()),
        );

        if (null === $label) {
            $this->logger->info('EnrichLoginLocationMessageHandler : localisation non résolue', [
                'loginHistoryId' => $message->loginHistoryId,
            ]);

            return;
        }

        $loginHistory->setLocation($label);
        $this->entityManager->flush();
    }
}
