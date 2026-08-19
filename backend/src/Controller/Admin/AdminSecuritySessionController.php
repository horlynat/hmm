<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\UserSession;
use App\Message\SessionRevokedNotification;
use App\Repository\UserRepository;
use App\Repository\UserSessionRepository;
use App\Security\Voter\SecurityVoter;
use App\Service\AuditLogger;
use App\Service\DeviceParser;
use App\Service\GeolocationService;
use App\Service\LiveSessionStateResolver;
use App\Service\SessionAnomalyDetector;
use App\Service\SessionCsvExporter;
use App\Service\UserSessionRevoker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sessions actives : sessions PHP stockées en base (PdoSessionHandler) corrélées à un
 * utilisateur via UserSession. "Forcer la déconnexion" tue à la fois la session active
 * et tous les jetons remember-me de l'utilisateur (déconnexion complète), et prévient
 * l'utilisateur par email (cf. SessionRevokedNotification).
 *
 * 🔒 Sécurité : liste réservée à SecurityVoter::MANAGE_SESSIONS, révocation à
 * SecurityVoter::FORCE_LOGOUT (ROLE_ADMIN et plus dans les deux cas). Les actions
 * groupées (revokeAll/revokeForUser/purgeEnded) restent sous MANAGE_SESSIONS :
 * FORCE_LOGOUT exige un User précis en sujet (cf. SecurityVoter) et ne s'exprime
 * donc qu'action par action — MANAGE_SESSIONS exige déjà exactement le même rôle
 * (ROLE_ADMIN), pas de contournement de permission en regroupant les révocations.
 */
#[Route('/admin/security/sessions', name: 'admin_security_session_')]
class AdminSecuritySessionController extends AbstractController
{
    private const INACTIVE_ACCOUNT_DAYS = 30;
    private const LIMIT = 20;
    private const ALLOWED_SORTS = ['createdAt', 'lastActivityAt', 'user', 'ip'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserSessionRevoker $userSessionRevoker,
        private readonly LiveSessionStateResolver $liveSessionStateResolver,
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        UserSessionRepository $userSessionRepository,
        UserRepository $userRepository,
        DeviceParser $deviceParser,
        GeolocationService $geolocationService,
        SessionAnomalyDetector $anomalyDetector,
    ): Response {
        $this->denyAccessUnlessGranted(SecurityVoter::MANAGE_SESSIONS);

        $liveSessions = $this->liveSessionStateResolver->resolveAll();
        $currentSessionId = $request->getSession()->getId();

        // ── Assemblage : état + appareil + localisation (résolue en direct via le
        // cache de GeolocationService plutôt que via UserSession::loginHistory —
        // ce dernier n'est peuplé qu'après le passage du worker asynchrone
        // EnrichLoginLocationMessage, potentiellement pas encore fait pour une
        // session toute fraîche ; l'appel direct utilise le même cache, donc pas
        // de coût réseau supplémentaire une fois la première résolution faite,
        // et donne aussi les coordonnées nécessaires à la détection ci-dessous).
        // Uniquement pour les sessions ACTIVES : inutile de géolocaliser une
        // session terminée que personne ne consultera dans le détail.
        $allEntries = [];
        foreach ($userSessionRepository->findAllOrderedByCreatedAt() as $userSession) {
            $live = $liveSessions[$userSession->getSessionId()] ?? null;
            $state = match (true) {
                null === $live => 'ended',
                $live['active'] => 'active',
                default => 'expired',
            };

            $coordinates = null;
            $locationLabel = null;
            if ('active' === $state && null !== $userSession->getIp()) {
                $coordinates = $geolocationService->getLocationFromIp($userSession->getIp());
                $locationLabel = GeolocationService::formatLabel($coordinates);
            }

            $allEntries[] = [
                'session' => $userSession,
                'state' => $state,
                'lastActivityAt' => null !== $live ? (new \DateTimeImmutable())->setTimestamp($live['lastActivityAt']) : null,
                'device' => $deviceParser->parse($userSession->getUserAgent()),
                'location' => $locationLabel,
                'latitude' => $coordinates['latitude'] ?? null,
                'longitude' => $coordinates['longitude'] ?? null,
            ];
        }

        // ── Détection de voyage impossible — groupée par utilisateur (comparer les
        // sessions de deux comptes différents n'a pas de sens), sur les sessions
        // actives uniquement. Résultat : ensemble des sessionId impliquées dans au
        // moins une anomalie, pour poser un badge dans le tableau.
        $byUser = [];
        foreach ($allEntries as $entry) {
            if ('active' === $entry['state']) {
                $byUser[$entry['session']->getUser()->getId()][] = $entry;
            }
        }
        $flaggedSessionIds = [];
        foreach ($byUser as $userEntries) {
            if (count($userEntries) < 2) {
                continue;
            }

            $points = array_map(static fn (array $e) => [
                'sessionId' => $e['session']->getSessionId(),
                'latitude' => $e['latitude'],
                'longitude' => $e['longitude'],
                'at' => $e['lastActivityAt'] ?? $e['session']->getCreatedAt(),
            ], $userEntries);

            foreach ($anomalyDetector->detectImpossibleTravel($points) as $anomaly) {
                $flaggedSessionIds[$anomaly['sessionIdA']] = true;
                $flaggedSessionIds[$anomaly['sessionIdB']] = true;
            }
        }

        // ── KPIs — toujours calculés sur l'ensemble complet, jamais sur la vue
        // filtrée ci-dessous (comme les tuiles du dashboard central : un chiffre
        // qui varie selon un filtre de recherche serait trompeur en indicateur).
        $statusCounts = ['active' => 0, 'expired' => 0, 'ended' => 0];
        $activeUserIds = [];
        foreach ($allEntries as $entry) {
            ++$statusCounts[$entry['state']];
            if ('active' === $entry['state']) {
                $activeUserIds[$entry['session']->getUser()->getId()] = true;
            }
        }

        // ── Filtres — appliqués en mémoire volontairement : "state"/"location" ne
        // sont pas des colonnes en base (calculées côté PHP), et le volume de
        // sessions d'un back-office interne reste borné.
        $search = trim((string) $request->query->get('search', ''));
        $entries = $allEntries;
        if ('' !== $search) {
            $needle = mb_strtolower($search);
            $entries = array_values(array_filter($entries, static function (array $entry) use ($needle) {
                $session = $entry['session'];
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $session->getUser()->getEmail(),
                    $session->getUser()->getFullName(),
                    $session->getIp(),
                ])));

                return str_contains($haystack, $needle);
            }));
        }

        $status = $request->query->get('status', 'all');
        if (!in_array($status, ['active', 'expired', 'ended'], true)) {
            $status = 'all';
        } else {
            $entries = array_values(array_filter($entries, static fn (array $entry) => $entry['state'] === $status));
        }

        // ── Tri — colonnes whitelistées (anti-injection, même principe que les
        // autres explorateurs admin, ex. AdminDashboardController::projects()).
        $sort = $request->query->get('sort', 'createdAt');
        if (!in_array($sort, self::ALLOWED_SORTS, true)) {
            $sort = 'createdAt';
        }
        $direction = strtoupper((string) $request->query->get('direction', 'desc')) === 'ASC' ? 'ASC' : 'DESC';

        usort($entries, static function (array $a, array $b) use ($sort, $direction) {
            $valueOf = static fn (array $e) => match ($sort) {
                'lastActivityAt' => $e['lastActivityAt']?->getTimestamp() ?? 0,
                'user' => mb_strtolower($e['session']->getUser()->getFullName() ?? $e['session']->getUser()->getEmail()),
                'ip' => $e['session']->getIp() ?? '',
                default => $e['session']->getCreatedAt()->getTimestamp(),
            };

            $cmp = $valueOf($a) <=> $valueOf($b);

            return 'ASC' === $direction ? $cmp : -$cmp;
        });

        $page = max(1, (int) $request->query->get('page', 1));
        $totalPages = max(1, (int) ceil(count($entries) / self::LIMIT));
        $page = min($page, $totalPages);
        $pagedEntries = array_slice($entries, ($page - 1) * self::LIMIT, self::LIMIT);

        return $this->render('admin/security/sessions.html.twig', [
            'sessions' => $pagedEntries,
            'currentSessionId' => $currentSessionId,
            'totalSessionsCount' => count($allEntries),
            'statusCounts' => $statusCounts,
            'activeUsersCount' => count($activeUserIds),
            'flaggedSessionIds' => $flaggedSessionIds,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'direction' => strtolower($direction),
            'page' => $page,
            'totalPages' => $totalPages,
            'inactiveUsers' => $userRepository->findInactiveSince(self::INACTIVE_ACCOUNT_DAYS),
            'inactiveAccountDays' => self::INACTIVE_ACCOUNT_DAYS,
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(
        Request $request,
        UserSessionRepository $userSessionRepository,
        DeviceParser $deviceParser,
        GeolocationService $geolocationService,
        SessionCsvExporter $exporter,
    ): Response {
        $this->denyAccessUnlessGranted(SecurityVoter::MANAGE_SESSIONS);

        $liveSessions = $this->liveSessionStateResolver->resolveAll();

        $rows = (function () use ($userSessionRepository, $liveSessions, $deviceParser, $geolocationService, $exporter): iterable {
            foreach ($userSessionRepository->findAllOrderedByCreatedAt() as $userSession) {
                $live = $liveSessions[$userSession->getSessionId()] ?? null;
                $state = match (true) {
                    null === $live => 'ended',
                    $live['active'] => 'active',
                    default => 'expired',
                };

                $location = null;
                if ('active' === $state && null !== $userSession->getIp()) {
                    $location = GeolocationService::formatLabel($geolocationService->getLocationFromIp($userSession->getIp()));
                }

                yield $exporter->row([
                    'session' => $userSession,
                    'state' => $state,
                    'lastActivityAt' => null !== $live ? (new \DateTimeImmutable())->setTimestamp($live['lastActivityAt']) : null,
                    'device' => $deviceParser->parse($userSession->getUserAgent()),
                    'location' => $location,
                ]);
            }
        })();

        return $exporter->stream('sessions.csv', $exporter->headers(), $rows);
    }

    #[Route('/{id}/revoke', name: 'revoke', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function revoke(int $id, Request $request, UserSessionRepository $userSessionRepository, AuditLogger $auditLogger): Response
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

        $user = $userSession->getUser();
        $this->userSessionRevoker->forceLogout($userSession);
        $auditLogger->log(User::class, (int) $user->getId(), $user->getEmail(), 'force_logout');
        $this->entityManager->flush();
        $this->notifyRevoked($userSession, $user);

        $this->addFlash('success', sprintf('%s a été déconnecté de force.', $user->getEmail()));

        return $this->redirectToRoute('admin_security_session_index');
    }

    /**
     * Déconnecte de force toutes les sessions autres que celle en cours — y compris
     * les autres sessions de l'admin qui déclenche l'action : "toutes les autres"
     * s'entend au sens strict, ce navigateur-ci reste connecté, tout le reste tombe.
     * Pensé pour une reprise en main après suspicion de compromission, quand
     * révoquer ligne par ligne serait trop lent.
     */
    #[Route('/revoke-all', name: 'revoke_all', methods: ['POST'])]
    public function revokeAll(Request $request, UserSessionRepository $userSessionRepository, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(SecurityVoter::MANAGE_SESSIONS);

        if (!$this->isCsrfTokenValid('admin_security_session_revoke_all', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Action annulée.');

            return $this->redirectToRoute('admin_security_session_index');
        }

        $currentSessionId = $request->getSession()->getId();
        $revokedCount = 0;

        foreach ($userSessionRepository->findAllOrderedByCreatedAt() as $userSession) {
            if ($userSession->getSessionId() === $currentSessionId) {
                continue;
            }

            $user = $userSession->getUser();
            $this->userSessionRevoker->forceLogout($userSession);
            $auditLogger->log(User::class, (int) $user->getId(), $user->getEmail(), 'force_logout_all');
            $this->notifyRevoked($userSession, $user);
            ++$revokedCount;
        }

        $this->entityManager->flush();

        $this->addFlash('success', $revokedCount > 0
            ? sprintf('%d session(s) déconnectée(s) de force. Seule cette session reste active.', $revokedCount)
            : 'Aucune autre session à déconnecter.');

        return $this->redirectToRoute('admin_security_session_index');
    }

    /**
     * Déconnecte de force TOUTES les sessions d'UN utilisateur précis (autre que
     * la session en cours, garde-fou identique à revokeAll) — bouton "Déconnecter
     * partout" depuis sa fiche compte (admin/users/read, admin/admins/read).
     */
    #[Route('/user/{id}/revoke-all', name: 'revoke_for_user', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function revokeForUser(int $id, Request $request, UserRepository $userRepository, UserSessionRepository $userSessionRepository, AuditLogger $auditLogger): Response
    {
        $user = $userRepository->find($id);
        if (!$user) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(SecurityVoter::FORCE_LOGOUT, $user);

        if (!$this->isCsrfTokenValid('admin_security_session_revoke_user_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Action annulée.');

            return $this->redirectBackOrToSessions($request);
        }

        $currentSessionId = $request->getSession()->getId();
        $revokedCount = 0;

        foreach ($userSessionRepository->findByUserOrderedByCreatedAt($user) as $userSession) {
            if ($userSession->getSessionId() === $currentSessionId) {
                continue;
            }

            $this->userSessionRevoker->forceLogout($userSession);
            $revokedCount++;
        }

        if ($revokedCount > 0) {
            $auditLogger->log(User::class, (int) $user->getId(), $user->getEmail(), 'force_logout_everywhere');
            $this->entityManager->flush();
            $this->notifyRevoked(null, $user);
        }

        $this->addFlash('success', $revokedCount > 0
            ? sprintf('%s a été déconnecté de partout (%d session(s)).', $user->getEmail(), $revokedCount)
            : sprintf('%s n\'avait aucune session active à déconnecter.', $user->getEmail()));

        return $this->redirectBackOrToSessions($request);
    }

    /**
     * Purge les lignes UserSession dont la session PHP correspondante a déjà
     * expiré et été nettoyée par le garbage collector natif (état "Terminée") —
     * ces lignes n'ont plus de session vivante à révoquer, seulement de
     * l'historique qui s'accumulerait sinon indéfiniment. Aucun accès actif
     * n'est affecté (contrairement à revoke/revokeAll) : pas d'AuditLogger ni
     * d'email ici, ce n'est pas une action de sécurité mais du nettoyage de données.
     */
    #[Route('/purge-ended', name: 'purge_ended', methods: ['POST'])]
    public function purgeEnded(Request $request, UserSessionRepository $userSessionRepository): Response
    {
        $this->denyAccessUnlessGranted(SecurityVoter::MANAGE_SESSIONS);

        if (!$this->isCsrfTokenValid('admin_security_session_purge_ended', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Action annulée.');

            return $this->redirectToRoute('admin_security_session_index');
        }

        $liveSessionIds = array_flip(array_keys($this->liveSessionStateResolver->resolveAll()));

        $purgedCount = 0;
        foreach ($userSessionRepository->findAllOrderedByCreatedAt() as $userSession) {
            if (isset($liveSessionIds[$userSession->getSessionId()])) {
                continue;
            }

            $this->entityManager->remove($userSession);
            ++$purgedCount;
        }

        $this->entityManager->flush();

        $this->addFlash('success', $purgedCount > 0
            ? sprintf('%d session(s) terminée(s) purgée(s).', $purgedCount)
            : 'Aucune session terminée à purger.');

        return $this->redirectToRoute('admin_security_session_index');
    }

    /**
     * Prévient l'utilisateur par email qu'une de ses sessions a été révoquée de
     * force (cf. SessionRevokedNotification) — jamais dispatché pour l'éviction
     * "douce" par limite de sessions concurrentes (LoginListener), qui n'a rien
     * d'une action de sécurité à signaler.
     */
    private function notifyRevoked(?UserSession $userSession, User $user): void
    {
        $admin = $this->getUser();
        $revokedByLabel = $admin instanceof User ? ($admin->getFullName() ?? $admin->getEmail()) : null;

        $this->bus->dispatch(new SessionRevokedNotification(
            userId: (int) $user->getId(),
            email: $user->getEmail(),
            fullName: $user->getFullName() ?? $user->getEmail(),
            ip: $userSession?->getIp(),
            device: $userSession?->getUserAgent(),
            date: new \DateTimeImmutable(),
            revokedByLabel: $revokedByLabel,
        ));
    }

    /**
     * Renvoie vers la page d'où l'action a été déclenchée (fiche utilisateur)
     * plutôt que systématiquement vers la liste des sessions — mais seulement si
     * le Referer pointe vers ce même hôte (garde-fou anti open-redirect, même
     * principe que SecurityAuthenticator::safeRelativePath() : le risque est
     * déjà quasi nul grâce au CSRF ci-dessus, cette vérification est une
     * seconde ligne de défense peu coûteuse).
     */
    private function redirectBackOrToSessions(Request $request): Response
    {
        $referer = $request->headers->get('referer');
        if (null !== $referer && parse_url($referer, \PHP_URL_HOST) === $request->getHost()) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('admin_security_session_index');
    }
}
