<?php

namespace App\Security\Voter;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Class UserVoter
 *
 * Contrôle centralisé des accès et des actions sensibles sur l'entité User.
 * Utilisé par les trois panneaux de gestion de comptes (Admins, Collaborateurs, Clients)
 * pour garantir les mêmes règles quel que soit le contrôleur qui charge l'utilisateur.
 *
 * Règles implémentées :
 * - VIEW            : Réservé aux administrateurs.
 * - EDIT            : Réservé aux administrateurs. Un compte Super Administrateur ne peut être
 *                      édité (y compris ses rôles) que par un autre Super Administrateur.
 * - DELETE          : Réservé aux administrateurs. Interdiction de se supprimer soi-même.
 *                      Un compte Super Administrateur ne peut être supprimé que par un autre
 *                      Super Administrateur.
 * - BAN             : Modérateur et plus. Mêmes garde-fous que EDIT (self / super-admin).
 * - VERIFY          : Modérateur et plus. Mêmes garde-fous que EDIT (self / super-admin).
 * - RESET_PASSWORD  : Manager et plus. Mêmes garde-fous que EDIT (self / super-admin).
 * - CHANGE_ROLE     : Administrateur et plus. Mêmes garde-fous que EDIT (self / super-admin).
 * - IMPERSONATE     : Super Administrateur uniquement. Impossible sur soi-même.
 *
 * @extends Voter<string, User>
 */
class UserVoter extends Voter
{
    use RoleHierarchyAwareTrait;

    public const VIEW = 'USER_VIEW';
    public const EDIT = 'USER_EDIT';
    public const DELETE = 'USER_DELETE';
    public const BAN = 'USER_BAN';
    public const VERIFY = 'USER_VERIFY';
    public const RESET_PASSWORD = 'USER_RESET_PASSWORD';
    public const CHANGE_ROLE = 'USER_CHANGE_ROLE';
    public const IMPERSONATE = 'USER_IMPERSONATE';

    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly LoggerInterface $logger,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW, self::EDIT, self::DELETE,
            self::BAN, self::VERIFY, self::RESET_PASSWORD, self::CHANGE_ROLE, self::IMPERSONATE,
        ], true)
            && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /** @var User $targetUser */
        $targetUser = $subject;

        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            $this->logger->warning('Tentative d\'accès refusée : utilisateur non authentifié ou jeton invalide.', [
                'action'         => $attribute,
                'target_user_id' => $targetUser->getId(),
            ]);
            return false;
        }

        $decision = match ($attribute) {
            self::VIEW           => $this->hasRole($currentUser, 'ROLE_ADMIN') && $this->canView($targetUser, $currentUser),
            self::EDIT           => $this->hasRole($currentUser, 'ROLE_ADMIN') && $this->canEdit($targetUser, $currentUser),
            self::DELETE         => $this->hasRole($currentUser, 'ROLE_ADMIN') && $this->canDelete($targetUser, $currentUser),
            self::BAN            => $this->hasRole($currentUser, 'ROLE_MODERATOR') && $this->canEdit($targetUser, $currentUser),
            self::VERIFY         => $this->hasRole($currentUser, 'ROLE_MODERATOR') && $this->canEdit($targetUser, $currentUser),
            self::RESET_PASSWORD => $this->hasRole($currentUser, 'ROLE_MANAGER') && $this->canEdit($targetUser, $currentUser),
            self::CHANGE_ROLE    => $this->hasRole($currentUser, 'ROLE_ADMIN') && $this->canEdit($targetUser, $currentUser),
            self::IMPERSONATE    => $this->hasRole($currentUser, 'ROLE_SUPER_ADMIN') && $targetUser->getId() !== $currentUser->getId(),
            default              => false,
        };

        $this->logger->info("Décision d'autorisation UserVoter évaluée", [
            'current_user_id' => $currentUser->getId(),
            'target_user_id'  => $targetUser->getId(),
            'action'          => $attribute,
            'decision'        => $decision ? 'GRANTED' : 'DENIED',
        ]);

        return $decision;
    }

    /**
     * Règle de lecture.
     * Tout administrateur peut consulter n'importe quel compte.
     */
    private function canView(User $target, User $current): bool
    {
        return true;
    }

    /**
     * Règle d'édition.
     * Un compte Super Administrateur ne peut être modifié que par un autre
     * Super Administrateur, pour empêcher un administrateur "simple" de
     * dégrader ou détourner le compte le plus privilégié.
     */
    private function canEdit(User $target, User $current): bool
    {
        if ($this->isSuperAdmin($target) && !$this->isSuperAdmin($current)) {
            return false;
        }

        return true;
    }

    /**
     * Règle de suppression (action critique).
     * - Un administrateur ne peut jamais se supprimer lui-même (évite un verrouillage accidentel).
     * - Un compte Super Administrateur ne peut être supprimé que par un autre Super Administrateur.
     */
    private function canDelete(User $target, User $current): bool
    {
        if ($target->getId() === $current->getId()) {
            return false;
        }

        if ($this->isSuperAdmin($target) && !$this->isSuperAdmin($current)) {
            return false;
        }

        return true;
    }

    private function isSuperAdmin(User $user): bool
    {
        return in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);
    }
}
