<?php

namespace App\Controller\Admin;

use App\Repository\FailedLoginAttemptRepository;
use App\Repository\UserRepository;
use App\Security\Voter\SecurityVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Rapport de conformité des politiques de sécurité en vigueur.
 *
 * Page en lecture seule : les règles affichées (complexité mot de passe,
 * rate-limit de connexion, durée du remember-me) reflètent ce qui est déjà
 * appliqué ailleurs dans le code (User, framework.yaml, security.yaml) —
 * il n'y a pas encore de mécanisme de configuration éditable (prévu en
 * phase 2 si besoin).
 *
 * 🔒 Sécurité : réservé à SecurityVoter::VIEW_POLICIES (ROLE_ADMIN et plus).
 */
#[Route('/admin/security/policies', name: 'admin_security_policy_')]
class AdminSecurityPolicyController extends AbstractController
{
    private const PASSWORD_MAX_AGE_DAYS = 90;
    private const SUSPICIOUS_IP_WINDOW_HOURS = 1;
    private const SUSPICIOUS_IP_MIN_ATTEMPTS = 3;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        UserRepository $userRepository,
        FailedLoginAttemptRepository $failedLoginAttemptRepository,
    ): Response {
        $this->denyAccessUnlessGranted(SecurityVoter::VIEW_POLICIES);

        return $this->render('admin/security/policies.html.twig', [
            'passwordMaxAgeDays' => self::PASSWORD_MAX_AGE_DAYS,
            'staleUsers' => $userRepository->findWithStalePassword(self::PASSWORD_MAX_AGE_DAYS),
            'suspiciousIps' => $failedLoginAttemptRepository->findSuspiciousIps(
                new \DateInterval(sprintf('PT%dH', self::SUSPICIOUS_IP_WINDOW_HOURS)),
                self::SUSPICIOUS_IP_MIN_ATTEMPTS,
            ),
            'suspiciousIpWindowHours' => self::SUSPICIOUS_IP_WINDOW_HOURS,
            'suspiciousIpMinAttempts' => self::SUSPICIOUS_IP_MIN_ATTEMPTS,
        ]);
    }
}
