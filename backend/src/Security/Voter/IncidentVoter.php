<?php

namespace App\Security\Voter;

use App\Entity\Incident;

/**
 * Permissions sur le journal d'incidents (App\Entity\Incident).
 *
 * VIEW/CREATE/EDIT réservés à ROLE_ADMIN — c'est un outil de suivi interne,
 * pas de la modération de contenu courant. DELETE réservé à ROLE_SUPER_ADMIN
 * et volontairement rare : supprimer un incident casse l'historique de
 * récurrence que cet espace sert justement à construire (cf.
 * IncidentRepository::countByCategory) — une correction se fait en éditant
 * le statut/la remédiation, pas en effaçant la trace.
 */
class IncidentVoter extends AbstractRoleVoter
{
    public const VIEW = 'INCIDENT_VIEW';
    public const CREATE = 'INCIDENT_CREATE';
    public const EDIT = 'INCIDENT_EDIT';
    public const DELETE = 'INCIDENT_DELETE';

    protected function getRequiredRole(string $attribute, mixed $subject): ?string
    {
        return match (true) {
            self::VIEW === $attribute && (null === $subject || $subject instanceof Incident) => 'ROLE_ADMIN',
            self::CREATE === $attribute && null === $subject => 'ROLE_ADMIN',
            self::EDIT === $attribute && $subject instanceof Incident => 'ROLE_ADMIN',
            self::DELETE === $attribute && $subject instanceof Incident => 'ROLE_SUPER_ADMIN',
            default => null,
        };
    }
}
