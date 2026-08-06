<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Journal générique "qui a fait quoi, quand" pour les entités du back-office qui n'ont
 * pas leur propre historique dédié (contrairement à Project → ProjectHistory).
 *
 * Ne flush pas elle-même : l'entrée est persistée pour être portée par le flush()
 * déjà présent dans l'action appelante (même logique que Project::addToHistory()).
 */
class AuditLogger
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function log(string $entityClass, int $entityId, string $entityLabel, string $action, ?string $details = null): void
    {
        $user = $this->security->getUser();

        $auditLog = new AuditLog(
            entityClass: $entityClass,
            entityId: $entityId,
            entityLabel: $entityLabel,
            action: $action,
            user: $user instanceof User ? $user : null,
            details: $details,
        );

        $this->entityManager->persist($auditLog);
    }
}
