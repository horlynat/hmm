<?php

namespace App\Repository;

use App\Entity\NotificationPreference;
use App\Enum\NotificationPriorityEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationPreference>
 */
class NotificationPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationPreference::class);
    }

    public function findByPriority(NotificationPriorityEnum $priority): ?NotificationPreference
    {
        return $this->findOneBy(['priority' => $priority]);
    }

    /**
     * Garantit qu'une ligne existe pour chaque niveau d'importance (créées avec
     * email activé par défaut, ce qui reproduit le comportement actuel de
     * notifier.yaml tant que l'admin n'a rien changé).
     *
     * @return array<string, NotificationPreference> Indexé par NotificationPriorityEnum::value
     */
    public function ensureDefaults(): array
    {
        $em = $this->getEntityManager();
        $existing = [];
        foreach ($this->findAll() as $preference) {
            $existing[$preference->getPriority()->value] = $preference;
        }

        $dirty = false;
        foreach (NotificationPriorityEnum::all() as $priority) {
            if (!isset($existing[$priority->value])) {
                $preference = new NotificationPreference($priority);
                $em->persist($preference);
                $existing[$priority->value] = $preference;
                $dirty = true;
            }
        }

        if ($dirty) {
            $em->flush();
        }

        return $existing;
    }
}
