<?php

namespace App\Security\Voter;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectStatusEnum;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Class ProjectVoter
 *
 * Contrôle centralisé des accès et des actions sensibles sur l'entité Project.
 * Ce voter garantit que les règles métiers de sécurité sont appliquées uniformément.
 * 
 * Règles implémentées :
 * - VIEW   : Le client à qui le projet est confié, les collaborateurs (équipe de réalisation) et toi (admin) pouvez consulter le projet.
 * - EDIT   : Réservé à toi (admin), à condition que le projet soit actif (non terminé/suspendu).
 * - DELETE : Réservé à toi (admin) — action critique.
 */
class ProjectVoter extends Voter
{
    public const VIEW   = 'PROJECT_VIEW';
    public const EDIT   = 'PROJECT_EDIT';
    public const DELETE = 'PROJECT_DELETE';

    /**
     * @param LoggerInterface $logger Service de journalisation (déclaré en readonly pour la sécurité d'exécution)
     */
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Détermine si ce Voter doit traiter la demande d'autorisation.
     * 
     * @param string $attribute L'action demandée (ex: PROJECT_VIEW)
     * @param mixed  $subject   L'objet sur lequel porte l'action
     * 
     * @return bool True si le voter prend en charge l'attribut et que le sujet est un Projet.
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        // Utilisation du mode strict (true) pour in_array afin d'éviter les fausses correspondances de type
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof Project;
    }

    /**
     * Évalue l'accès en fonction des règles métiers.
     * 
     * @param string         $attribute L'action demandée (VIEW, EDIT, DELETE)
     * @param mixed          $subject   L'instance de Project (typée mixed pour respecter la signature du parent)
     * @param TokenInterface $token     Le jeton contenant l'utilisateur connecté
     * @param Vote|null      $vote      Paramètre optionnel du moteur de sécurité Symfony
     * 
     * @return bool True si l'accès est accordé, False sinon.
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /** @var Project $project */
        $project = $subject;

        $user = $token->getUser();
        
        // Sécurité primaire : L'utilisateur doit être une instance valide de notre entité User[cite: 1]
        if (!$user instanceof User) {
            $this->logger->warning('Tentative d\'accès refusée : Utilisateur non authentifié ou jeton invalide.', [
                'action'     => $attribute,
                'project_id' => $project->getId()
            ]);
            return false;
        }

        // Aiguillage vers les méthodes métiers spécifiques[cite: 1]
        $decision = match ($attribute) {
            self::VIEW   => $this->canView($project, $user),
            self::EDIT   => $this->canEdit($project, $user),
            self::DELETE => $this->canDelete($project, $user),
            default      => false,
        };

        // Journalisation système pour l'audit et le débogage[cite: 1]
        $this->logger->info("Décision d'autorisation ProjectVoter évaluée", [
            'user_id'    => $user->getId(),
            'project_id' => $project->getId(),
            'action'     => $attribute,
            'status'     => $project->getStatus()->value,
            'decision'   => $decision ? 'GRANTED' : 'DENIED',
        ]);

        // Historisation métier : On garde une trace interne des tentatives d'accès non autorisées[cite: 1]
        if (!$decision) {
            $project->addToHistory(
                'access_denied', 
                $user, 
                sprintf("Tentative d'action '%s' refusée par les règles de sécurité.", $attribute)
            );
        }

        return $decision;
    }

    /**
     * Règle de lecture.
     * Le projet est visible par le client à qui il est confié, par les
     * collaborateurs qui participent à sa réalisation, ou par un
     * administrateur (toi).
     * 
     * @param Project $project
     * @param User    $user
     * @return bool
     */
    private function canView(Project $project, User $user): bool
    {
        return $project->getClient() === $user
            || $project->getCollaborators()->contains($user)
            || in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    /**
     * Règle d'édition.
     * Réservée aux administrateurs. L'édition est verrouillée si le projet
     * est dans un état finalisé ou suspendu, même pour un administrateur.
     * 
     * @param Project $project
     * @param User    $user
     * @return bool
     */
    private function canEdit(Project $project, User $user): bool
    {
        // 1. Vérification de l'état du projet (Prioritaire sur les rôles)
        if (in_array($project->getStatus(), [ProjectStatusEnum::COMPLETED, ProjectStatusEnum::SUSPENDED], true)) {
            return false;
        }

        // 2. Seul un administrateur peut éditer un projet
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    /**
     * Règle de suppression (Action critique).
     * Réservée aux administrateurs.
     * 
     * @param Project $project
     * @param User    $user
     * @return bool
     */
    private function canDelete(Project $project, User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }
}
