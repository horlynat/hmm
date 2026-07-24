<?php

// src/EventListener/LoginListener.php

namespace App\EventListener;

use App\Entity\FailedLoginAttempt;
use App\Entity\LoginHistory;
use App\Entity\User;
use App\Entity\UserSession;
use App\Enum\NotificationPriorityEnum;
use App\Message\LoginNotification;
use App\Repository\FailedLoginAttemptRepository;
use App\Repository\UserRepository;
use App\Service\AdminAlertNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
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

    public function __construct(
        private MessageBusInterface $bus,
        private RequestStack $requestStack,
        private EntityManagerInterface $entityManager,
        private FailedLoginAttemptRepository $failedLoginAttemptRepository,
        private AdminAlertNotifier $adminAlertNotifier,
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
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
        );

        $this->entityManager->persist($loginHistory);
        $this->entityManager->persist($userSession);
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
            // Statut du compte (UserChecker) : le message porte le mot-clé.
            $exception instanceof AccountStatusException => match (true) {
                str_contains($exception->getMessage(), 'vérifié') => 'unverified_account',
                str_contains($exception->getMessage(), 'désactivé') => 'inactive_account',
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

        $attempt = new FailedLoginAttempt(
            email: $email,
            reason: $reason,
            ip: $request->getClientIp(),
            userAgent: $request->headers->get('User-Agent'),
        );

        $this->entityManager->persist($attempt);
        $this->entityManager->flush();

        $this->alertIfSuspicious($attempt);
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
