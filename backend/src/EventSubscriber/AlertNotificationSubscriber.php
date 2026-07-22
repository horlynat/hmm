<?php

namespace App\EventSubscriber;

use App\Entity\ContactMessage;
use App\Entity\QuoteRequest;
use App\Entity\Testimonial;
use App\Enum\NotificationPriorityEnum;
use App\Service\AdminAlertNotifier;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;

/**
 * Alerte l'admin à la création d'un ContactMessage / QuoteRequest / Testimonial,
 * quelle que soit l'origine (API Platform côté front public, ou back-office) :
 * postPersist est le seul point de passage commun à ces deux chemins.
 *
 * L'envoi est différé à postFlush (et non fait directement dans postPersist) :
 * postPersist se déclenche pendant que la transaction de flush est encore
 * ouverte, donc avant que l'entité ne soit garantie d'être commit. Alerter à
 * ce moment risquerait de notifier pour une création qui finit par échouer
 * (rollback) plus loin dans le même flush.
 */
class AlertNotificationSubscriber implements EventSubscriber
{
    /** @var array<int, array{priority: NotificationPriorityEnum, subject: string, content: string}> */
    private array $pending = [];

    public function __construct(private readonly AdminAlertNotifier $adminAlertNotifier)
    {
    }

    /** @return string[] */
    public function getSubscribedEvents(): array
    {
        return [Events::postPersist, Events::postFlush];
    }

    /** @param LifecycleEventArgs<ObjectManager> $args */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        $alert = match (true) {
            $entity instanceof ContactMessage => [
                'priority' => NotificationPriorityEnum::MEDIUM,
                'subject' => 'Nouveau message de contact',
                'content' => sprintf(
                    "De : %s <%s>\nSujet : %s\n\n%s",
                    $entity->getName(),
                    $entity->getEmail(),
                    $entity->getSubject(),
                    $entity->getMessage(),
                ),
            ],
            $entity instanceof QuoteRequest => [
                'priority' => NotificationPriorityEnum::HIGH,
                'subject' => 'Nouvelle demande de devis',
                'content' => sprintf(
                    "De : %s <%s> (%s)\n\n%s",
                    $entity->getName(),
                    $entity->getEmail(),
                    $entity->getPhone(),
                    $entity->getMessage(),
                ),
            ],
            $entity instanceof Testimonial => [
                'priority' => NotificationPriorityEnum::LOW,
                'subject' => 'Nouveau témoignage à valider',
                'content' => sprintf('%s a laissé un témoignage en attente de validation.', $entity->getAuthor()),
            ],
            default => null,
        };

        if (null !== $alert) {
            $this->pending[] = $alert;
        }
    }

    public function postFlush(): void
    {
        if ([] === $this->pending) {
            return;
        }

        $pending = $this->pending;
        $this->pending = [];

        foreach ($pending as $alert) {
            $this->adminAlertNotifier->alert($alert['priority'], $alert['subject'], $alert['content']);
        }
    }
}
