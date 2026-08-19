<?php

namespace App\Controller\Admin;

use App\Entity\BlockedIp;
use App\Entity\User;
use App\Repository\BlockedIpRepository;
use App\Repository\FailedLoginAttemptRepository;
use App\Repository\UserRepository;
use App\Security\Voter\SecurityVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Rapport de conformité des politiques de sécurité en vigueur, complété par
 * la gestion des IPs bloquées (cf. App\Entity\BlockedIp,
 * App\EventSubscriber\IpBlockSubscriber, seul point d'application du blocage).
 *
 * Les règles affichées (complexité mot de passe, rate-limit de connexion,
 * durée du remember-me) reflètent ce qui est déjà appliqué ailleurs dans le
 * code (User, framework.yaml, security.yaml) — pas encore de mécanisme de
 * configuration éditable pour celles-ci (prévu en phase 2 si besoin). Le
 * blocage d'IP, lui, est une vraie action : c'est la seule mutation de cette
 * page.
 *
 * 🔒 Sécurité : lecture réservée à SecurityVoter::VIEW_POLICIES, blocage/
 * déblocage à SecurityVoter::MANAGE_IP_BLOCKS (ROLE_ADMIN et plus dans les
 * deux cas).
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
        BlockedIpRepository $blockedIpRepository,
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
            'blockedIps' => $blockedIpRepository->findAllOrderedByCreatedAt(),
        ]);
    }

    /**
     * Blocage manuel (formulaire IP + motif) ou rapide depuis une ligne du
     * rapport "IPs suspectes" (motif pré-rempli côté template, cf.
     * policies.html.twig) — même action pour les deux, la seule différence
     * est qui remplit le champ `reason`.
     */
    #[Route('/ip/block', name: 'block_ip', methods: ['POST'])]
    public function blockIp(Request $request, EntityManagerInterface $entityManager, BlockedIpRepository $blockedIpRepository, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(SecurityVoter::MANAGE_IP_BLOCKS);

        if (!$this->isCsrfTokenValid('admin_security_policy_block_ip', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Action annulée.');

            return $this->redirectToRoute('admin_security_policy_index');
        }

        $ip = trim((string) $request->request->get('ip'));
        $reason = trim((string) $request->request->get('reason'));

        if ('' === $ip || false === filter_var($ip, \FILTER_VALIDATE_IP)) {
            $this->addFlash('error', 'Adresse IP invalide.');

            return $this->redirectToRoute('admin_security_policy_index');
        }

        if ('' === $reason) {
            $reason = 'Blocage manuel, sans motif renseigné.';
        }

        if ($blockedIpRepository->isBlocked($ip)) {
            $this->addFlash('error', sprintf('%s est déjà bloquée.', $ip));

            return $this->redirectToRoute('admin_security_policy_index');
        }

        $admin = $this->getUser();
        $blockedByLabel = $admin instanceof User ? ($admin->getFullName() ?? $admin->getEmail()) : null;

        $blockedIp = new BlockedIp(ip: $ip, reason: $reason, blockedByLabel: $blockedByLabel);
        $entityManager->persist($blockedIp);
        $entityManager->flush();

        $auditLogger->log(BlockedIp::class, (int) $blockedIp->getId(), $ip, 'ip_blocked');
        $entityManager->flush();

        $this->addFlash('success', sprintf('%s a été bloquée.', $ip));

        return $this->redirectBack($request);
    }

    #[Route('/ip/{id}/unblock', name: 'unblock_ip', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function unblockIp(int $id, Request $request, EntityManagerInterface $entityManager, BlockedIpRepository $blockedIpRepository, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(SecurityVoter::MANAGE_IP_BLOCKS);

        $blockedIp = $blockedIpRepository->find($id);
        if (!$blockedIp) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('admin_security_policy_unblock_ip_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Action annulée.');

            return $this->redirectToRoute('admin_security_policy_index');
        }

        $ip = $blockedIp->getIp();
        $auditLogger->log(BlockedIp::class, $id, $ip, 'ip_unblocked');
        $entityManager->remove($blockedIp);
        $entityManager->flush();

        $this->addFlash('success', sprintf('%s a été débloquée.', $ip));

        return $this->redirectToRoute('admin_security_policy_index');
    }

    /**
     * Renvoie vers la page d'où l'action a été déclenchée (logs de connexion,
     * si le blocage a été fait depuis là) plutôt que systématiquement vers
     * les politiques — même garde-fou anti open-redirect que
     * AdminSecuritySessionController::redirectBackOrToSessions().
     */
    private function redirectBack(Request $request): Response
    {
        $referer = $request->headers->get('referer');
        if (null !== $referer && parse_url($referer, \PHP_URL_HOST) === $request->getHost()) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('admin_security_policy_index');
    }
}
