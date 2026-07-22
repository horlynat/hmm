<?php

namespace App\Notifier;

use App\Enum\NotificationPriorityEnum;
use App\Repository\NotificationPreferenceRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\Channel\ChannelPolicyInterface;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;

/**
 * Remplace le service "notifier.channel_policy" par défaut (framework-bundle,
 * config statique de notifier.yaml) par une version pilotée depuis l'admin
 * (voir AdminNotificationController / NotificationPreference).
 *
 * $fallbackPolicy (= la policy statique de notifier.yaml) sert de filet de
 * sécurité : si la table n'existe pas encore (avant migration) ou si la base
 * est indisponible, les notifications continuent de partir plutôt que de
 * planter silencieusement.
 */
final class DatabaseChannelPolicy implements ChannelPolicyInterface
{
    /** @param array<string, string[]> $fallbackPolicy */
    public function __construct(
        private readonly NotificationPreferenceRepository $preferenceRepository,
        private readonly LoggerInterface $logger,
        private readonly array $fallbackPolicy,
    ) {
    }

    /** @return string[] */
    public function getChannels(string $importance): array
    {
        $priority = NotificationPriorityEnum::tryFrom($importance);
        if (null === $priority) {
            throw new InvalidArgumentException(sprintf('Importance "%s" is not defined in the Policy.', $importance));
        }

        try {
            $preference = $this->preferenceRepository->findByPriority($priority);
        } catch (\Throwable $e) {
            $this->logger->warning('DatabaseChannelPolicy : lecture des préférences impossible, repli sur notifier.yaml.', [
                'importance' => $importance,
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackPolicy[$importance] ?? [];
        }

        if (null === $preference) {
            return $this->fallbackPolicy[$importance] ?? [];
        }

        $channels = [];
        if ($preference->isEmailEnabled()) {
            $channels[] = 'email';
        }
        if ($preference->isPushEnabled()) {
            $channels[] = 'push';
        }

        return $channels;
    }
}
