<?php

namespace App\Controller\Admin;

use App\Entity\FailedLoginAttempt;
use App\Entity\LoginHistory;
use App\Repository\BlockedIpRepository;
use App\Repository\FailedLoginAttemptRepository;
use App\Repository\LoginHistoryRepository;
use App\Security\Voter\SecurityVoter;
use App\Service\AuditLogger;
use App\Service\DeviceParser;
use App\Service\GeolocationService;
use App\Service\SecurityLogCsvExporter;
use App\Service\SecurityLogRetentionPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Journal des connexions : réussites (LoginHistory) et tentatives échouées
 * (FailedLoginAttempt). Complété par : appareil/localisation lisibles
 * (au lieu du user-agent brut / rien du tout), tri par colonne, plage de
 * dates, export CSV, un signal "IP suspecte" croisé avec
 * FailedLoginAttemptRepository::findSuspiciousIps() (déjà utilisé par le
 * rapport de politiques) directement sur chaque ligne — avec un accès rapide
 * au blocage (AdminSecurityPolicyController::blockIp()) sans changer de page —,
 * une vue détail par ligne avec corrélation (showSuccess/showFailed), et une
 * purge (manuelle ici, automatique via app:security-log:purge en cron, cf.
 * SecurityLogRetentionPolicy pour la durée de rétention).
 *
 * 🔒 Sécurité : lecture (index/export/show*) réservée à SecurityVoter::VIEW_LOGS,
 * purge à SecurityVoter::MANAGE_LOGS (ROLE_ADMIN et plus dans les deux cas).
 * Le blocage lui-même reste dans AdminSecurityPolicyController (une seule
 * action d'écriture pour les deux pages qui l'exposent, cf. son docblock).
 */
#[Route('/admin/security/logs', name: 'admin_security_log_')]
class AdminSecurityLogController extends AbstractController
{
    private const LIMIT = 20;
    private const SUSPICIOUS_WINDOW_HOURS = 1;
    private const SUSPICIOUS_MIN_ATTEMPTS = 3;
    private const ALLOWED_SUCCESS_SORTS = ['loginAt', 'ip', 'user'];
    private const ALLOWED_FAILED_SORTS = ['createdAt', 'ip', 'email'];

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        LoginHistoryRepository $loginHistoryRepository,
        FailedLoginAttemptRepository $failedLoginAttemptRepository,
        BlockedIpRepository $blockedIpRepository,
        DeviceParser $deviceParser,
        GeolocationService $geolocationService,
    ): Response {
        $this->denyAccessUnlessGranted(SecurityVoter::VIEW_LOGS);

        $tab = 'failed' === $request->query->get('tab', 'success') ? 'failed' : 'success';
        $search = trim((string) $request->query->get('search', ''));
        $from = $this->parseDate($request->query->get('from'));
        $to = $this->parseDate($request->query->get('to'), endOfDay: true);

        $requestedSort = (string) $request->query->get('sort', '');
        $allowedSorts = 'failed' === $tab ? self::ALLOWED_FAILED_SORTS : self::ALLOWED_SUCCESS_SORTS;
        $sort = in_array($requestedSort, $allowedSorts, true) ? $requestedSort : ('failed' === $tab ? 'createdAt' : 'loginAt');
        $direction = 'ASC' === strtoupper((string) $request->query->get('direction', 'desc')) ? 'ASC' : 'DESC';

        $queryBuilder = 'failed' === $tab
            ? $this->buildFailedQueryBuilder($failedLoginAttemptRepository, $search, $from, $to, $sort, $direction)
            : $this->buildSuccessQueryBuilder($loginHistoryRepository, $search, $from, $to, $sort, $direction);

        $page = max(1, (int) $request->query->get('page', 1));
        $paginator = new Paginator($queryBuilder);
        $totalPages = max(1, (int) ceil($paginator->count() / self::LIMIT));
        $page = min($page, $totalPages);

        $rawEntries = $paginator->getQuery()
            ->setFirstResult(($page - 1) * self::LIMIT)
            ->setMaxResults(self::LIMIT)
            ->getResult();

        $suspiciousIps = $failedLoginAttemptRepository->findSuspiciousIps(
            new \DateInterval(sprintf('PT%dH', self::SUSPICIOUS_WINDOW_HOURS)),
            self::SUSPICIOUS_MIN_ATTEMPTS,
        );
        $suspiciousIpSet = array_flip(array_map(static fn (array $row) => $row['ip'], $suspiciousIps));
        $blockedIpSet = array_flip(array_map(static fn ($b) => $b->getIp(), $blockedIpRepository->findAllOrderedByCreatedAt()));

        $entries = array_map(function ($entry) use ($tab, $deviceParser, $geolocationService, $suspiciousIpSet, $blockedIpSet) {
            $ip = $entry->getIp();

            return [
                'entry' => $entry,
                'device' => $deviceParser->parse('failed' === $tab ? $entry->getUserAgent() : $entry->getDevice()),
                'location' => 'failed' === $tab
                    ? (null !== $ip ? GeolocationService::formatLabel($geolocationService->getLocationFromIp($ip)) : null)
                    : $entry->getLocation(),
                'isSuspicious' => null !== $ip && isset($suspiciousIpSet[$ip]),
                'isBlocked' => null !== $ip && isset($blockedIpSet[$ip]),
            ];
        }, $rawEntries);

        $since24h = new \DateTimeImmutable('-24 hours');
        $loginRetentionThreshold = new \DateTimeImmutable(sprintf('-%d days', SecurityLogRetentionPolicy::LOGIN_HISTORY_RETENTION_DAYS));
        $failedRetentionThreshold = new \DateTimeImmutable(sprintf('-%d days', SecurityLogRetentionPolicy::FAILED_ATTEMPT_RETENTION_DAYS));

        return $this->render('admin/security/logs.html.twig', [
            'tab' => $tab,
            'entries' => $entries,
            'search' => $search,
            'from' => $request->query->get('from', ''),
            'to' => $request->query->get('to', ''),
            'sort' => $sort,
            'direction' => strtolower($direction),
            'page' => $page,
            'totalPages' => $totalPages,
            'successCount24h' => $loginHistoryRepository->countSince($since24h),
            'failedCount24h' => $failedLoginAttemptRepository->countSince($since24h),
            'distinctUsers24h' => $loginHistoryRepository->countDistinctUsersSince($since24h),
            'suspiciousIpsCount' => count($suspiciousIps),
            'suspiciousWindowHours' => self::SUSPICIOUS_WINDOW_HOURS,
            'purgeableLoginsCount' => $loginHistoryRepository->countOlderThan($loginRetentionThreshold),
            'purgeableFailedCount' => $failedLoginAttemptRepository->countOlderThan($failedRetentionThreshold),
            'loginRetentionDays' => SecurityLogRetentionPolicy::LOGIN_HISTORY_RETENTION_DAYS,
            'failedRetentionDays' => SecurityLogRetentionPolicy::FAILED_ATTEMPT_RETENTION_DAYS,
            'totalLoginsCount' => $loginHistoryRepository->countAll(),
            'totalFailedCount' => $failedLoginAttemptRepository->countAll(),
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(
        Request $request,
        LoginHistoryRepository $loginHistoryRepository,
        FailedLoginAttemptRepository $failedLoginAttemptRepository,
        SecurityLogCsvExporter $exporter,
    ): Response {
        $this->denyAccessUnlessGranted(SecurityVoter::VIEW_LOGS);

        $tab = 'failed' === $request->query->get('tab', 'success') ? 'failed' : 'success';
        $search = trim((string) $request->query->get('search', ''));
        $from = $this->parseDate($request->query->get('from'));
        $to = $this->parseDate($request->query->get('to'), endOfDay: true);

        if ('failed' === $tab) {
            $queryBuilder = $this->buildFailedQueryBuilder($failedLoginAttemptRepository, $search, $from, $to, 'createdAt', 'DESC');
            $rows = (static function () use ($queryBuilder, $exporter): iterable {
                foreach ($queryBuilder->getQuery()->toIterable() as $entry) {
                    yield $exporter->failedRow($entry);
                }
            })();

            return $exporter->stream('tentatives-echouees.csv', $exporter->failedHeaders(), $rows);
        }

        $queryBuilder = $this->buildSuccessQueryBuilder($loginHistoryRepository, $search, $from, $to, 'loginAt', 'DESC');
        $rows = (static function () use ($queryBuilder, $exporter): iterable {
            foreach ($queryBuilder->getQuery()->toIterable() as $entry) {
                yield $exporter->successRow($entry);
            }
        })();

        return $exporter->stream('connexions-reussies.csv', $exporter->successHeaders(), $rows);
    }

    /**
     * Vue détail d'une connexion réussie — le tableau de la liste ne peut pas
     * tout montrer (user-agent complet, coordonnées précises) ni corréler
     * (autres connexions du même compte, tentatives échouées ayant précédé
     * celle-ci depuis la même IP ou visant le même email — utile pour repérer
     * un bruteforce qui a fini par réussir).
     */
    #[Route('/success/{id}', name: 'show_success', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showSuccess(
        int $id,
        LoginHistoryRepository $loginHistoryRepository,
        FailedLoginAttemptRepository $failedLoginAttemptRepository,
        BlockedIpRepository $blockedIpRepository,
        DeviceParser $deviceParser,
        GeolocationService $geolocationService,
    ): Response {
        $this->denyAccessUnlessGranted(SecurityVoter::VIEW_LOGS);

        $entry = $loginHistoryRepository->find($id);
        if (!$entry) {
            throw $this->createNotFoundException();
        }

        $ip = $entry->getIp();
        $email = $entry->getUser()->getEmail();

        return $this->render('admin/security/log_show.html.twig', [
            'tab' => 'success',
            'entry' => $entry,
            'ip' => $ip,
            'email' => $email,
            'device' => $deviceParser->parse($entry->getDevice()),
            'location' => $entry->getLocation(),
            'coordinates' => null !== $ip ? $geolocationService->getLocationFromIp($ip) : null,
            'isBlocked' => null !== $ip && $blockedIpRepository->isBlocked($ip),
            'relatedLogins' => $loginHistoryRepository->findRecentByUser($entry->getUser(), 10, $entry->getId()),
            'relatedFailedByIp' => null !== $ip ? $failedLoginAttemptRepository->findRecentByIp($ip, 10) : [],
            'relatedFailedByEmail' => $failedLoginAttemptRepository->findRecentByEmail($email, 10),
        ]);
    }

    /**
     * Vue détail d'une tentative échouée — mêmes corrélations que
     * showSuccess() (autres tentatives depuis la même IP, autres tentatives
     * visant le même email, et connexions réussies pour cet email si le
     * compte existe réellement).
     */
    #[Route('/failed/{id}', name: 'show_failed', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showFailed(
        int $id,
        FailedLoginAttemptRepository $failedLoginAttemptRepository,
        LoginHistoryRepository $loginHistoryRepository,
        BlockedIpRepository $blockedIpRepository,
        DeviceParser $deviceParser,
        GeolocationService $geolocationService,
    ): Response {
        $this->denyAccessUnlessGranted(SecurityVoter::VIEW_LOGS);

        $entry = $failedLoginAttemptRepository->find($id);
        if (!$entry) {
            throw $this->createNotFoundException();
        }

        $ip = $entry->getIp();
        $coordinates = null !== $ip ? $geolocationService->getLocationFromIp($ip) : null;

        return $this->render('admin/security/log_show.html.twig', [
            'tab' => 'failed',
            'entry' => $entry,
            'ip' => $ip,
            'email' => $entry->getEmail(),
            'device' => $deviceParser->parse($entry->getUserAgent()),
            'location' => GeolocationService::formatLabel($coordinates),
            'coordinates' => $coordinates,
            'isBlocked' => null !== $ip && $blockedIpRepository->isBlocked($ip),
            'relatedLogins' => $loginHistoryRepository->findRecentByEmail($entry->getEmail(), 5),
            'relatedFailedByIp' => null !== $ip ? $failedLoginAttemptRepository->findRecentByIp($ip, 10, $entry->getId()) : [],
            'relatedFailedByEmail' => $failedLoginAttemptRepository->findRecentByEmail($entry->getEmail(), 10, $entry->getId()),
        ]);
    }

    /**
     * Purge manuelle immédiate — même durée de rétention que la commande cron
     * app:security-log:purge (SecurityLogRetentionPolicy, source unique),
     * pour ne pas attendre le prochain passage planifié quand un admin veut
     * nettoyer tout de suite (ex: juste après avoir constaté la volumétrie).
     *
     * Contrairement à AdminSecuritySessionController::purgeEnded() (lignes
     * techniques déjà mortes, sans valeur d'audit), ce qui est supprimé ici
     * EST le journal de sécurité lui-même — sa suppression doit donc être
     * elle-même auditée (AuditLogger), sans quoi un admin pourrait effacer
     * des preuves sans laisser de trace. Même logique côté cron, cf.
     * SecurityLogPurgeCommand.
     */
    #[Route('/purge', name: 'purge', methods: ['POST'])]
    public function purge(Request $request, LoginHistoryRepository $loginHistoryRepository, FailedLoginAttemptRepository $failedLoginAttemptRepository, AuditLogger $auditLogger, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(SecurityVoter::MANAGE_LOGS);

        if (!$this->isCsrfTokenValid('admin_security_log_purge', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Action annulée.');

            return $this->redirectToRoute('admin_security_log_index');
        }

        $deletedLogins = $loginHistoryRepository->deleteOlderThan(new \DateTimeImmutable(sprintf('-%d days', SecurityLogRetentionPolicy::LOGIN_HISTORY_RETENTION_DAYS)));
        $deletedFailed = $failedLoginAttemptRepository->deleteOlderThan(new \DateTimeImmutable(sprintf('-%d days', SecurityLogRetentionPolicy::FAILED_ATTEMPT_RETENTION_DAYS)));
        $total = $deletedLogins + $deletedFailed;

        if ($deletedLogins > 0) {
            $auditLogger->log(LoginHistory::class, 0, sprintf('%d ligne(s)', $deletedLogins), 'security_log_purged', sprintf('Purge manuelle : %d connexion(s) réussie(s) supprimée(s) (> %d jours).', $deletedLogins, SecurityLogRetentionPolicy::LOGIN_HISTORY_RETENTION_DAYS));
        }
        if ($deletedFailed > 0) {
            $auditLogger->log(FailedLoginAttempt::class, 0, sprintf('%d ligne(s)', $deletedFailed), 'security_log_purged', sprintf('Purge manuelle : %d tentative(s) échouée(s) supprimée(s) (> %d jours).', $deletedFailed, SecurityLogRetentionPolicy::FAILED_ATTEMPT_RETENTION_DAYS));
        }
        if ($total > 0) {
            $entityManager->flush();
        }

        $this->addFlash('success', $total > 0
            ? sprintf('%d connexion(s) réussie(s) et %d tentative(s) échouée(s) purgées (au-delà de la durée de rétention).', $deletedLogins, $deletedFailed)
            : 'Rien à purger : aucun log au-delà de la durée de rétention.');

        return $this->redirectToRoute('admin_security_log_index');
    }

    /**
     * Purge totale immédiate — supprime TOUT le journal, y compris les
     * entrées récentes, sans condition de rétention (contrairement à purge()
     * ci-dessus). Utile pour vider un environnement de dev/démo ; en prod,
     * préférer purge() sauf besoin explicite. Mêmes garde-fous (CSRF, audit)
     * qu'une suppression partielle — supprimer le journal de sécurité
     * lui-même doit toujours laisser une trace.
     */
    #[Route('/purge-all', name: 'purge_all', methods: ['POST'])]
    public function purgeAll(Request $request, LoginHistoryRepository $loginHistoryRepository, FailedLoginAttemptRepository $failedLoginAttemptRepository, AuditLogger $auditLogger, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(SecurityVoter::MANAGE_LOGS);

        if (!$this->isCsrfTokenValid('admin_security_log_purge_all', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Action annulée.');

            return $this->redirectToRoute('admin_security_log_index');
        }

        $deletedLogins = $loginHistoryRepository->deleteAll();
        $deletedFailed = $failedLoginAttemptRepository->deleteAll();
        $total = $deletedLogins + $deletedFailed;

        if ($deletedLogins > 0) {
            $auditLogger->log(LoginHistory::class, 0, sprintf('%d ligne(s)', $deletedLogins), 'security_log_purged_all', sprintf('Purge totale : %d connexion(s) réussie(s) supprimée(s) (sans condition de rétention).', $deletedLogins));
        }
        if ($deletedFailed > 0) {
            $auditLogger->log(FailedLoginAttempt::class, 0, sprintf('%d ligne(s)', $deletedFailed), 'security_log_purged_all', sprintf('Purge totale : %d tentative(s) échouée(s) supprimée(s) (sans condition de rétention).', $deletedFailed));
        }
        if ($total > 0) {
            $entityManager->flush();
        }

        $this->addFlash('success', $total > 0
            ? sprintf('Purge totale : %d connexion(s) réussie(s) et %d tentative(s) échouée(s) supprimées.', $deletedLogins, $deletedFailed)
            : 'Rien à purger : le journal est déjà vide.');

        return $this->redirectToRoute('admin_security_log_index');
    }

    private function buildSuccessQueryBuilder(LoginHistoryRepository $repository, string $search, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, string $sort, string $direction): QueryBuilder
    {
        $queryBuilder = $repository->createQueryBuilder('l')
            ->leftJoin('l.user', 'u')
            ->addSelect('u')
            ->orderBy('user' === $sort ? 'u.email' : 'l.' . $sort, $direction);

        if ('' !== $search) {
            $queryBuilder->andWhere('u.email LIKE :search OR l.ip LIKE :search')->setParameter('search', '%' . $search . '%');
        }
        if (null !== $from) {
            $queryBuilder->andWhere('l.loginAt >= :from')->setParameter('from', $from);
        }
        if (null !== $to) {
            $queryBuilder->andWhere('l.loginAt <= :to')->setParameter('to', $to);
        }

        return $queryBuilder;
    }

    private function buildFailedQueryBuilder(FailedLoginAttemptRepository $repository, string $search, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, string $sort, string $direction): QueryBuilder
    {
        $queryBuilder = $repository->createQueryBuilder('f')
            ->orderBy('f.' . $sort, $direction);

        if ('' !== $search) {
            $queryBuilder->andWhere('f.email LIKE :search OR f.ip LIKE :search')->setParameter('search', '%' . $search . '%');
        }
        if (null !== $from) {
            $queryBuilder->andWhere('f.createdAt >= :from')->setParameter('from', $from);
        }
        if (null !== $to) {
            $queryBuilder->andWhere('f.createdAt <= :to')->setParameter('to', $to);
        }

        return $queryBuilder;
    }

    private function parseDate(?string $value, bool $endOfDay = false): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }

        return $endOfDay ? $date->setTime(23, 59, 59) : $date->setTime(0, 0, 0);
    }
}
