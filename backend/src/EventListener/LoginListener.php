<?php

// src/EventListener/LoginListener.php

namespace App\EventListener;

use App\Entity\FailedLoginAttempt;
use App\Entity\LoginHistory;
use App\Entity\User;
use App\Entity\UserSession;
use App\Enum\NotificationPriorityEnum;
use App\Message\CheckSessionAnomaliesMessage;
use App\Message\EnrichLoginLocationMessage;
use App\Message\LoginNotification;
use App\Security\LockedAccountAuthenticationException;
use App\Repository\FailedLoginAttemptRepository;
use App\Repository\UserRepository;
use App\Repository\UserSessionRepository;
use App\Service\AdminAlertNotifier;
use App\Service\LiveSessionStateResolver;
use App\Service\SessionAnomalyDetector;
use App\Service\UserSessionRevoker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class)]
#[AsEventListener(event: InteractiveLoginEvent::class, method: 'onLogin')]
#[AsEventListener(event: LoginFailureEvent::class, method: 'onLoginFailure')]
class LoginListener
{
    /** Fenêtre et seuil identiques à ceux du rapport "IPs suspectes" (AdminSecurityPolicyController). */
    private const SUSPICIOUS_WINDOW_HOURS = 1;
    private const SUSPICIOUS_MIN_ATTEMPTS = 3;

    /*
     * Le verrouillage automatique de compte (User::$lockedUntil après 5
     * échecs) a été remplacé par un délai progressif par (e-mail, IP) —
     * App\Security\LoginThrottle, appliqué AVANT la vérification du mot de
     * passe (P1 #10) : le verrou dur était un DoS ciblé réarmable à l'infini
     * et, pendant le verrou, un oracle de mot de passe.
     */

    /**
     * Firewalls sur lesquels un LoginSuccessEvent représente une vraie connexion
     * interactive. Le firewall `api` (JWT stateless, cf. security.yaml) est
     * volontairement exclu : sans session, Symfony ré-authentifie le Bearer
     * token depuis zéro à CHAQUE requête, donc LoginSuccessEvent s'y déclenche
     * sur chaque clic/rafraîchissement — pas seulement à la connexion. Sans ce
     * garde-fou, __invoke() renvoyait un email de sécurité (et flushait
     * lastIp/lastDevice/lastLoginAt) à chaque appel `/api/*` d'un client
     * connecté, saturant le serveur SMTP.
     */
    private const INTERACTIVE_FIREWALLS = ['main', 'api_login'];

    public function __construct(
        private MessageBusInterface $bus,
        private RequestStack $requestStack,
        private EntityManagerInterface $entityManager,
        private FailedLoginAttemptRepository $failedLoginAttemptRepository,
        private AdminAlertNotifier $adminAlertNotifier,
        private UserRepository $userRepository,
        private UserSessionRepository $userSessionRepository,
        private LiveSessionStateResolver $liveSessionStateResolver,
        private UserSessionRevoker $userSessionRevoker,
        private SessionAnomalyDetector $anomalyDetector,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        if (!\in_array($event->getFirewallName(), self::INTERACTIVE_FIREWALLS, true)) {
            return;
        }

        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $ip = $request->getClientIp() ?? '0.0.0.0';
        $device = $request->headers->get('User-Agent') ?? 'Appareil inconnu';

        // Les setters tronquent à la longueur de colonne : un User-Agent
        // démesuré ne peut plus faire échouer la connexion elle-même.
        $user->setLastIp($ip);
        $user->setLastDevice($device);
        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->bus->dispatch(new LoginNotification(
            userId: $user->getId(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            ip: $ip,
            device: $device,
            date: new \DateTimeImmutable(),
        ));
    }

    public function onLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        if (!$user instanceof User) {
            return;
        }

        $request = $event->getRequest();

        $loginHistory = new LoginHistory();
        $loginHistory->setUser($user);
        $loginHistory->setIp($request->getClientIp());
        $loginHistory->setDevice($request->headers->get('User-Agent'));
        $loginHistory->setLoginAt(new \DateTimeImmutable());

        $userSession = new UserSession(
            user: $user,
            sessionId: $request->getSession()->getId(),
            ip: $request->getClientIp(),
            userAgent: $request->headers->get('User-Agent'),
            loginHistory: $loginHistory,
        );

        $this->entityManager->persist($loginHistory);
        $this->entityManager->persist($userSession);
        $this->entityManager->flush();

        $this->enforceConcurrentSessionLimit($user);

        $this->bus->dispatch(new EnrichLoginLocationMessage($loginHistory->getId()));
        $this->bus->dispatch(new CheckSessionAnomaliesMessage($user->getId()));
    }

    /**
     * Au-delà de SessionAnomalyDetector::DEFAULT_MAX_CONCURRENT_SESSIONS sessions
     * actives pour ce compte, évince les plus anciennes — jamais la session qui
     * vient de s'ouvrir. Éviction "douce" (UserSessionRevoker::killLiveSession,
     * pas forceLogout) : un appareil évincé qui a un cookie remember-me valide
     * pourra rouvrir une session normalement à sa prochaine requête, ce n'est
     * qu'une limite de capacité, pas une sanction de sécurité.
     */
    private function enforceConcurrentSessionLimit(User $user): void
    {
        $liveSessions = $this->liveSessionStateResolver->resolveAll();

        $activeSessions = array_values(array_filter(
            $this->userSessionRepository->findByUserOrderedByCreatedAt($user),
            static fn (UserSession $s) => $liveSessions[$s->getSessionId()]['active'] ?? false,
        ));

        $toEvict = $this->anomalyDetector->selectSessionsExceedingLimit($activeSessions);
        if ([] === $toEvict) {
            return;
        }

        foreach ($toEvict as $session) {
            $this->userSessionRevoker->killLiveSession($session);
        }

        $this->entityManager->flush();
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $email = trim((string) $request->getPayload()->getString('email'));
        if ('' === $email) {
            return;
        }

        $exception = $event->getException();
        $reason = match (true) {
            $exception instanceof LockedAccountAuthenticationException => 'locked_account',
            $exception instanceof TooManyLoginAttemptsAuthenticationException => 'rate_limited',
            // Statut du compte (UserChecker) : le message porte le mot-clé.
            $exception instanceof AccountStatusException => match (true) {
                str_contains($exception->getMessage(), 'vérifié') => 'unverified_account',
                str_contains($exception->getMessage(), 'désactivé') => 'inactive_account',
                str_contains($exception->getMessage(), 'verrouillé') => 'locked_account',
                str_contains($exception->getMessage(), 'expiré') => 'expired_account',
                default => 'account_status',
            },
            $exception instanceof CustomUserMessageAuthenticationException
                && str_contains($exception->getMessage(), 'tentatives') => 'rate_limited',
            // « email inconnu » vs « mauvais mot de passe » n'est plus distingué
            // par le message renvoyé au client (anti-énumération) : on reconstitue
            // la distinction ici, côté serveur, uniquement pour le journal admin —
            // jamais exposée à l'attaquant.
            null === $this->userRepository->findOneBy(['email' => $email]) => 'unknown_user',
            default => 'bad_credentials',
        };

        // Le constructeur tronque chaque champ à sa longueur de colonne (un
        // User-Agent > 255 ou un e-mail > 180 faisait échouer le flush en
        // MySQL strict : ni journal, ni alerte). Et une erreur d'écriture
        // résiduelle ne doit jamais casser la réponse d'échec elle-même — le
        // délai anti-bruteforce (LoginThrottleListener) est déjà compté.
        $attempt = new FailedLoginAttempt(
            email: $email,
            reason: $reason,
            ip: $request->getClientIp(),
            userAgent: $request->headers->get('User-Agent'),
        );

        try {
            $this->entityManager->persist($attempt);
            $this->entityManager->flush();
            $this->alertIfSuspicious($attempt);
        } catch (\Throwable $e) {
            $this->logger->error('Journalisation d\'un échec de connexion impossible.', ['exception' => $e]);
        }
    }

    /**
     * Alerte une seule fois, au moment précis où le seuil est franchi (pas à
     * chaque tentative suivante) pour éviter de noyer l'admin sous les alertes
     * tant que l'IP continue d'échouer.
     */
    private function alertIfSuspicious(FailedLoginAttempt $attempt): void
    {
        $ip = $attempt->getIp();
        if (null === $ip) {
            return;
        }

        $count = $this->failedLoginAttemptRepository->countRecentByIp(
            $ip,
            new \DateInterval(sprintf('PT%dH', self::SUSPICIOUS_WINDOW_HOURS)),
        );

        if (self::SUSPICIOUS_MIN_ATTEMPTS !== $count) {
            return;
        }

        $this->adminAlertNotifier->alert(
            NotificationPriorityEnum::URGENT,
            'Activité de connexion suspecte',
            sprintf(
                "%d tentatives de connexion échouées depuis l'IP %s au cours de la dernière heure (dernier email tenté : %s).",
                $count,
                $ip,
                $attempt->getEmail(),
            ),
        );
    }
}
