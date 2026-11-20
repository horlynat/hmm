<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\SuperAdminElevationContext;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * PAM (Privileged Access Management) en libre-service : ROLE_SUPER_ADMIN
 * reste "dormant" par défaut (cf. User::$superAdminElevatedUntil) — un
 * compte qui EN A le droit doit l'activer explicitement, pour une fenêtre
 * limitée, avant de pouvoir l'exercer. Jamais de Super Admin permanent actif.
 *
 * Re-saisie du mot de passe requise (step-up), avec rate-limiting dédié :
 * même sans second approbateur humain (structure trop petite pour un vrai
 * flux d'approbation), l'activation ne doit pas être un simple clic — c'est
 * la "validation" que demande tout outillage PAM sérieux.
 *
 * Pas de paramètre {id} : on ne gère jamais l'élévation d'un AUTRE compte
 * ici, uniquement celle de l'appelant (même principe que TwoFactorController).
 *
 * L'élévation est liée à CETTE session (marqueur de session, cf.
 * SuperAdminElevationContext) : elle ne s'étend jamais aux JWT du compte ni à
 * ses autres sessions. Le jeton de sécurité de la session est reconstruit
 * avec les nouveaux rôles, sans quoi le ContextListener verrait des rôles
 * différents à la requête suivante et déconnecterait l'utilisateur.
 */
#[Route('/profile/elevation', name: 'profile_elevation_')]
#[IsGranted('ROLE_ADMIN')]
class PrivilegeElevationController extends AbstractController
{
    private const ELEVATION_DURATION_MINUTES = 30;

    #[Route('/activate', name: 'activate', methods: ['POST'])]
    public function activate(
        Request $request,
        TokenStorageInterface $tokenStorage,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        AuditLogger $auditLogger,
        #[Autowire(service: 'limiter.privilege_elevation')]
        RateLimiterFactory $privilegeElevationLimiter,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $redirect = $this->redirectToRoute('admin_profile_read', ['id' => $user->getId()]);

        if (!$user->hasSuperAdminEntitlement()) {
            $this->addFlash('error', 'Ce compte n\'a pas le droit Super Administrateur.');

            return $redirect;
        }

        if (!$this->isCsrfTokenValid('profile_elevation_activate', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Merci de réessayer.');

            return $redirect;
        }

        $limiter = $privilegeElevationLimiter->create($user->getUserIdentifier());
        if (!$limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Trop de tentatives. Patientez une minute avant de réessayer.');

            return $redirect;
        }

        $password = (string) $request->request->get('password', '');
        if ('' === $password || !$passwordHasher->isPasswordValid($user, $password)) {
            $this->addFlash('error', 'Mot de passe incorrect.');

            return $redirect;
        }

        // Seconde entière : l'horodatage sert de lien entre la base et le
        // marqueur de session (comparaison exacte).
        $until = (new \DateTimeImmutable())->setTimestamp(time() + self::ELEVATION_DURATION_MINUTES * 60);
        $user->setSuperAdminElevatedUntil($until);
        $auditLogger->log(User::class, (int) $user->getId(), $user->getEmail(), 'super_admin_elevated', sprintf('Jusqu\'à %s.', $until->format('d/m/Y H:i')));
        $entityManager->flush();

        $request->getSession()->set(SuperAdminElevationContext::SESSION_KEY, ['user' => $user->getUserIdentifier(), 'until' => $until->getTimestamp()]);
        SuperAdminElevationContext::grant($user->getUserIdentifier(), $until->getTimestamp());
        $this->refreshSecurityToken($tokenStorage, $user);

        $this->addFlash('success', sprintf('Mode Super Administrateur activé jusqu\'à %s.', $until->format('H:i')));

        return $redirect;
    }

    #[Route('/deactivate', name: 'deactivate', methods: ['POST'])]
    public function deactivate(Request $request, TokenStorageInterface $tokenStorage, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $redirect = $this->redirectToRoute('admin_profile_read', ['id' => $user->getId()]);

        if (!$this->isCsrfTokenValid('profile_elevation_deactivate', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Merci de réessayer.');

            return $redirect;
        }

        if ($user->isSuperAdminElevated()) {
            $user->setSuperAdminElevatedUntil(null);
            $auditLogger->log(User::class, (int) $user->getId(), $user->getEmail(), 'super_admin_deelevated');
            $entityManager->flush();
            $this->addFlash('success', 'Mode Super Administrateur désactivé.');
        }

        $request->getSession()->remove(SuperAdminElevationContext::SESSION_KEY);
        SuperAdminElevationContext::reset();
        $this->refreshSecurityToken($tokenStorage, $user);

        return $redirect;
    }

    private function refreshSecurityToken(TokenStorageInterface $tokenStorage, User $user): void
    {
        $token = $tokenStorage->getToken();
        if (null === $token || !method_exists($token, 'getFirewallName')) {
            return;
        }

        $tokenStorage->setToken(new PostAuthenticationToken($user, $token->getFirewallName(), $user->getRoles()));
    }
}
