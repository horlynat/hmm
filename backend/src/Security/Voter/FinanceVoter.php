<?php

namespace App\Security\Voter;

/**
 * Permissions sur le module Finance (aucun sujet : vues globales tous
 * projets confondus). Distinct de ProjectVoter::MANAGE_INVOICE, qui reste
 * scopé à un Project précis (ROLE_ADMIN + affecté) — un ROLE_MANAGER peut
 * donc consulter toutes les factures ici sans pouvoir forcément agir dessus
 * depuis l'écran projet correspondant : cohérent avec le module Finance qui
 * ne fait que consulter/filtrer/exporter, jamais muter.
 */
class FinanceVoter extends AbstractRoleVoter
{
    public const VIEW = 'FINANCE_VIEW';
    public const EXPORT = 'FINANCE_EXPORT';

    protected function getRequiredRole(string $attribute, mixed $subject): ?string
    {
        if (null !== $subject) {
            return null;
        }

        return match ($attribute) {
            self::VIEW => 'ROLE_MANAGER',
            self::EXPORT => 'ROLE_MANAGER',
            default => null,
        };
    }
}
