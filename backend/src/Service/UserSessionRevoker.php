<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserSession;
use App\EventSubscriber\SessionTokenVersionSubscriber;
use App\Repository\UserSessionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Point d'entrée unique pour détruire une session serveur — et, depuis le
 * correctif P1 #8, pour révoquer TOUT ce qui permet encore d'agir au nom d'un
 * compte. Trois portées, volontairement distinctes :
 *
 * - killLiveSession() ne touche que CETTE session (ligne `sessions` + entité
 *   UserSession). Un appareil qui a un cookie remember-me valide pourra
 *   rouvrir une session normalement à sa prochaine requête. C'est le
 *   comportement voulu pour une simple éviction de capacité : on ne
 *   sanctionne pas l'appareil le plus ancien, on libère juste une place.
 *
 * - forceLogout() : déconnexion forcée d'un compte à partir d'une de ses
 *   sessions (admin, ou révocation par l'utilisateur lui-même) = revokeAll()
 *   en conservant la session courante si elle appartient au même compte.
 *
 * - revokeAll() : purge complète — sessions web connues (UserSession),
 *   TOUS les jetons remember-me, et incrément de User::$tokenVersion qui
 *   invalide tous les JWT émis (claim `tv`) et toute session non répertoriée
 *   (restaurée par remember-me, ouverte avant le suivi…) via
 *   SessionTokenVersionSubscriber. Appelée à chaque changement ou
 *   réinitialisation de mot de passe, changement d'e-mail, suppression de
 *   compte et déconnexion forcée.
 *
 * Si la requête courante est une session web DU MÊME compte (l'utilisateur
 * change son propre mot de passe), elle est conservée et resynchronisée sur
 * la nouvelle version : il reste connecté sur cet appareil, tous les autres
 * tombent.
 *
 * N'effectue PAS le flush de l'EntityManager : laissé à l'appelant, pour
 * permettre de grouper plusieurs révocations dans une seule écriture.
 *
 * Contre-audit B2 (M2) : revokeAll() purge les remember-me de l'IDENTIFIANT
 * COURANT de $user. Si l'appelant a déjà changé l'e-mail en base avant
 * d'appeler revokeAll() (cas d'un Form Symfony, qui lie les données
 * soumises dès `handleRequest()`, avant toute validation), les lignes de
 * l'ANCIENNE adresse ne sont plus atteintes par cette requête et restent
 * valables : un cookie remember-me volé authentifierait alors le titulaire
 * suivant de cette adresse. purgeRememberMeFor() permet de viser
 * explicitement l'ancienne adresse, capturée par l'appelant avant le
 * changement.
 */
final class UserSessionRevoker
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly UserSessionRepository $userSessionRepository,
        private readonly RequestStack $requestStack,
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
        $this->revokeAll($userSession->getUser());
    }

    /**
     * @return int nombre de sessions web connues détruites
     */
    public function revokeAll(User $user): int
    {
        $currentSession = $this->currentSessionOf($user);
        $keepSessionId = $currentSession?->getId();

        $killed = 0;
        foreach ($this->userSessionRepository->findBy(['user' => $user]) as $userSession) {
            if (null !== $keepSessionId && $userSession->getSessionId() === $keepSessionId) {
                continue;
            }
            $this->killLiveSession($userSession);
            ++$killed;
        }

        $this->connection->executeStatement(
            'DELETE FROM rememberme_token WHERE username = :email',
            ['email' => $user->getUserIdentifier()],
        );

        $user->bumpTokenVersion();

        // La session de l'appareil qui agit (même compte) reste valide.
        $currentSession?->set(SessionTokenVersionSubscriber::SESSION_KEY, $user->getTokenVersion());

        return $killed;
    }

    /**
     * Purge les remember-me d'un e-mail qui n'est PLUS (ou pas encore) celui
     * d'aucun compte vivant à cette identité — typiquement l'ancienne
     * adresse d'un compte dont l'e-mail vient de changer en base avant cet
     * appel. revokeAll(), lui, ne vise que l'identifiant COURANT de $user.
     */
    public function purgeRememberMeFor(string $email): void
    {
        $this->connection->executeStatement(
            'DELETE FROM rememberme_token WHERE username = :email',
            ['email' => $email],
        );
    }

    private function currentSessionOf(User $user): ?\Symfony\Component\HttpFoundation\Session\SessionInterface
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request || !$request->hasSession() || str_starts_with($request->getPathInfo(), '/api')) {
            return null;
        }

        $session = $request->getSession();
        if (!$session->isStarted() && !$request->hasPreviousSession()) {
            return null;
        }

        return $session->get(SessionTokenVersionSubscriber::SESSION_USER_KEY) === $user->getUserIdentifier() ? $session : null;
    }
}
