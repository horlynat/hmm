<?php

namespace App\Security\Voter;

use App\Entity\User;
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
 * @extends Voter<string, mixed>
 */
abstract class AbstractRoleVoter extends Voter
{
    use RoleHierarchyAwareTrait;

    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly LoggerInterface $logger,
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

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $decision = $this->hasRole($user, $requiredRole);

        $this->logger->info(sprintf('%s : décision d\'autorisation évaluée', static::class), [
            'user_id'       => $user->getId(),
            'action'        => $attribute,
            'required_role' => $requiredRole,
            'decision'      => $decision ? 'GRANTED' : 'DENIED',
        ]);

        return $decision;
    }
}
