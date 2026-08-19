<?php

namespace App\Service;

use App\Entity\UserSession;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Point d'entrée unique pour détruire une session serveur — utilisé à la fois
 * par l'éviction automatique (limite de sessions concurrentes, cf.
 * LoginListener) et la déconnexion forcée manuelle
 * (AdminSecuritySessionController), qui n'ont volontairement PAS la même
 * portée :
 *
 * - killLiveSession() ne touche que CETTE session (ligne `sessions` + entité
 *   UserSession). Un appareil qui a un cookie remember-me valide pourra
 *   rouvrir une session normalement à sa prochaine requête. C'est le
 *   comportement voulu pour une simple éviction de capacité : on ne
 *   sanctionne pas l'appareil le plus ancien, on libère juste une place.
 *
 * - forceLogout() fait la même chose ET supprime TOUS les jetons
 *   remember-me de l'utilisateur : déconnexion complète, plus aucun appareil
 *   ne peut se reconnecter silencieusement. C'est le comportement voulu pour
 *   une action de sécurité explicite (admin, ou suspicion de compromission).
 *
 * N'effectue PAS le flush de l'EntityManager : laissé à l'appelant, pour
 * permettre de grouper plusieurs révocations dans une seule écriture.
 */
final class UserSessionRevoker
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
    ) {
    }

    public function killLiveSession(UserSession $userSession): void
    {
        $this->connection->executeStatement(
            'DELETE FROM sessions WHERE sess_id = :id',
            ['id' => $userSession->getSessionId()],
        );

        $this->entityManager->remove($userSession);
    }

    public function forceLogout(UserSession $userSession): void
    {
        $this->killLiveSession($userSession);

        $this->connection->executeStatement(
            'DELETE FROM rememberme_token WHERE username = :email',
            ['email' => $userSession->getUser()->getUserIdentifier()],
        );
    }
}
