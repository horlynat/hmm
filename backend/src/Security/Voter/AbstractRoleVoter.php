<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Service\PermissionRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Base commune aux Voters "à palier de rôle" : une permission est accordée dès
 * que l'utilisateur courant possède (ou hérite via la hiérarchie des rôles) le
 * rôle minimum requis pour l'action demandée. Pas de règle métier ici (pas de
 * notion de propriétaire, de statut verrouillé, etc.) — pour ce niveau de
 * finesse, un Voter dédié (ex: ProjectVoter, UserVoter) reste préférable.
 *
 * Le seuil retourné par getRequiredRole() (codé en dur dans chaque sous-
 * classe) reste la vérité de dernier recours, mais passe d'abord par
 * PermissionRegistry qui peut le remplacer par une valeur pilotée en base
 * (cf. son docblock pour les garanties fail-safe et le périmètre exact —
 * SecurityVoter/SettingsVoter ne sont jamais concernés même en passant par
 * cette classe commune).
 *
 * @extends Voter<string, mixed>
 */
abstract class AbstractRoleVoter extends Voter
{
    use RoleHierarchyAwareTrait;

    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly LoggerInterface $logger,
        private readonly PermissionRegistry $permissionRegistry,
    ) {
    }

    /**
     * Rôle minimum requis pour effectuer $attribute sur $subject, ou null si
     * cette combinaison attribut/sujet n'est pas gérée par ce Voter.
     */
    abstract protected function getRequiredRole(string $attribute, mixed $subject): ?string;

    protected function supports(string $attribute, mixed $subject): bool
    {
        return null !== $this->getRequiredRole($attribute, $subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $requiredRole = $this->getRequiredRole($attribute, $subject);
        if (null === $requiredRole) {
            return false;
        }

        $effectiveRole = $this->permissionRegistry->resolveRole($attribute, $requiredRole);

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $decision = $this->hasRole($user, $effectiveRole);

        $this->logger->info(sprintf('%s : décision d\'autorisation évaluée', static::class), [
            'user_id'       => $user->getId(),
            'action'        => $attribute,
            'required_role' => $effectiveRole,
            'coded_default' => $requiredRole,
            'decision'      => $decision ? 'GRANTED' : 'DENIED',
        ]);

        return $decision;
    }
}
