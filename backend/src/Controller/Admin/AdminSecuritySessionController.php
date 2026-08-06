<?php

namespace App\Controller\Admin;

use App\Repository\UserRepository;
use App\Repository\UserSessionRepository;
use App\Security\Voter\SecurityVoter;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sessions actives : sessions PHP stockées en base (PdoSessionHandler) corrélées à un
 * utilisateur via UserSession. "Forcer la déconnexion" tue à la fois la session active
 * et tous les jetons remember-me de l'utilisateur (déconnexion complète).
 *
 * 🔒 Sécurité : liste réservée à SecurityVoter::MANAGE_SESSIONS, révocation à
 * SecurityVoter::FORCE_LOGOUT (ROLE_ADMIN et plus dans les deux cas).
 */
#[Route('/admin/security/sessions', name: 'admin_security_session_')]
class AdminSecuritySessionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
    ) {
    }

    private const INACTIVE_ACCOUNT_DAYS = 30;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        UserSessionRepository $userSessionRepository,
        UserRepository $userRepository,
    ): Response {
        $this->denyAccessUnlessGranted(SecurityVoter::MANAGE_SESSIONS);

        // sess_id (VARBINARY) → expiration = sess_time + sess_lifetime. Une ligne
        // encore présente mais dont l'expiration est passée n'a pas encore été
        // nettoyée par le garbage collector des sessions : "expirée", pas "active".
        $rows = $this->connection->executeQuery('SELECT sess_id, sess_time, sess_lifetime FROM sessions')
            ->fetchAllAssociative();
        $now = time();
        $liveSessions = [];
        foreach ($rows as $row) {
            $liveSessions[$row['sess_id']] = ((int) $row['sess_time'] + (int) $row['sess_lifetime']) >= $now;
        }

        $sessions = array_map(
            static function ($userSession) use ($liveSessions) {
                $state = match (true) {
                    !array_key_exists($userSession->getSessionId(), $liveSessions) => 'ended',
                    $liveSessions[$userSession->getSessionId()] => 'active',
                    default => 'expired',
                };

                return ['session' => $userSession, 'state' => $state];
            },
            $userSessionRepository->findAllOrderedByCreatedAt(),
        );

        return $this->render('admin/security/sessions.html.twig', [
            'sessions' => $sessions,
            'currentSessionId' => $request->getSession()->getId(),
            'inactiveUsers' => $userRepository->findInactiveSince(self::INACTIVE_ACCOUNT_DAYS),
            'inactiveAccountDays' => self::INACTIVE_ACCOUNT_DAYS,
        ]);
    }

    #[Route('/{id}/revoke', name: 'revoke', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function revoke(int $id, Request $request, UserSessionRepository $userSessionRepository): Response
    {
        $userSession = $userSessionRepository->find($id);
        if (!$userSession) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(SecurityVoter::FORCE_LOGOUT, $userSession->getUser());

        if (!$this->isCsrfTokenValid('admin_security_session_revoke_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Action annulée.');
            return $this->redirectToRoute('admin_security_session_index');
        }

        $this->connection->executeStatement(
            'DELETE FROM sessions WHERE sess_id = :id',
            ['id' => $userSession->getSessionId()],
        );

        $this->connection->executeStatement(
            'DELETE FROM rememberme_token WHERE username = :email',
            ['email' => $userSession->getUser()->getUserIdentifier()],
        );

        $this->entityManager->remove($userSession);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s a été déconnecté de force.', $userSession->getUser()->getEmail()));

        return $this->redirectToRoute('admin_security_session_index');
    }
}
