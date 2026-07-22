<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface as TotpTwoFactorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Activation / désactivation de la 2FA (TOTP) en libre-service, pour le compte
 * actuellement connecté uniquement (pas de paramètre {id} : on ne gère jamais
 * la 2FA d'un autre compte ici — c'est le rôle de AdminSecurityTwoFactorController).
 *
 * Le secret TOTP n'est écrit en base qu'après vérification d'un code valide,
 * pour ne jamais activer une 2FA que l'utilisateur ne serait pas en mesure de
 * satisfaire (secret mal scanné, appli non synchronisée, etc.). Le secret en
 * attente de confirmation vit uniquement en session le temps du setup.
 */
#[Route('/profile/2fa', name: 'profile_two_factor_')]
#[IsGranted('ROLE_USER')]
class TwoFactorController extends AbstractController
{
    private const SESSION_KEY = 'two_factor_setup_secret';

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('profile/two_factor/index.html.twig', [
            'user' => $this->getCurrentUser(),
        ]);
    }

    #[Route('/setup', name: 'setup', methods: ['GET', 'POST'])]
    public function setup(
        Request $request,
        TotpAuthenticatorInterface $totpAuthenticator,
        EntityManagerInterface $entityManager,
        #[Autowire(service: 'limiter.two_factor_setup')]
        RateLimiterFactory $twoFactorSetupLimiter,
    ): Response {
        $user = $this->getCurrentUser();

        if ($user->isTotpAuthenticationEnabled()) {
            $this->addFlash('info', 'La double authentification est déjà activée sur ce compte.');

            return $this->redirectToRoute('profile_two_factor_index');
        }

        $session = $request->getSession();
        $secret = $session->get(self::SESSION_KEY);

        if (!is_string($secret) || $request->query->getBoolean('regenerate')) {
            $secret = $totpAuthenticator->generateSecret();
            $session->set(self::SESSION_KEY, $secret);
        }

        $pendingTotpUser = $this->createPendingTotpUser($user, $secret);
        $error = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('profile_two_factor_setup', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide. Merci de réessayer.');

                return $this->redirectToRoute('profile_two_factor_setup');
            }

            $limiter = $twoFactorSetupLimiter->create($user->getUserIdentifier());
            if (!$limiter->consume(1)->isAccepted()) {
                $error = 'Trop de tentatives, patientez une minute avant de réessayer.';
            } else {
                $code = (string) $request->request->get('code', '');

                if ($totpAuthenticator->checkCode($pendingTotpUser, $code)) {
                    $user->setTotpSecret($secret);
                    $user->setIsTwoFactorEnabled(true);
                    $entityManager->flush();
                    $session->remove(self::SESSION_KEY);

                    $this->addFlash('success', 'Double authentification activée. Gardez votre application d\'authentification à portée de main : elle sera demandée à chaque connexion.');

                    return $this->redirectToRoute('profile_two_factor_index');
                }

                $error = 'Code invalide. Vérifiez l\'heure de votre appareil et réessayez.';
            }
        }

        $qrCode = (new Builder(
            writer: new PngWriter(),
            data: $totpAuthenticator->getQRContent($pendingTotpUser),
            size: 240,
            margin: 8,
        ))->build();

        return $this->render('profile/two_factor/setup.html.twig', [
            'user' => $user,
            'secret' => $secret,
            'qrCodeDataUri' => $qrCode->getDataUri(),
            'error' => $error,
        ]);
    }

    #[Route('/disable', name: 'disable', methods: ['GET', 'POST'])]
    public function disable(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $user = $this->getCurrentUser();

        if (!$user->isTotpAuthenticationEnabled()) {
            return $this->redirectToRoute('profile_two_factor_index');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('profile_two_factor_disable', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide. Merci de réessayer.');

                return $this->redirectToRoute('profile_two_factor_disable');
            }

            $password = (string) $request->request->get('password', '');

            if ($passwordHasher->isPasswordValid($user, $password)) {
                $user->setTotpSecret(null);
                $user->setIsTwoFactorEnabled(false);
                $entityManager->flush();

                $this->addFlash('success', 'Double authentification désactivée.');

                return $this->redirectToRoute('profile_two_factor_index');
            }

            $error = 'Mot de passe incorrect.';
        }

        return $this->render('profile/two_factor/disable.html.twig', [
            'error' => $error,
        ]);
    }

    private function getCurrentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    /**
     * Utilisateur "fantôme" au secret en attente, uniquement pour générer/vérifier
     * le QR code — évite de muter l'entité Doctrine réelle avant confirmation.
     */
    private function createPendingTotpUser(User $user, string $secret): TotpTwoFactorInterface
    {
        return new class($user->getTotpAuthenticationUsername(), $secret) implements TotpTwoFactorInterface {
            public function __construct(
                private readonly ?string $username,
                private readonly string $secret,
            ) {
            }

            public function isTotpAuthenticationEnabled(): bool
            {
                return true;
            }

            public function getTotpAuthenticationUsername(): ?string
            {
                return $this->username;
            }

            public function getTotpAuthenticationConfiguration(): TotpConfigurationInterface
            {
                return new TotpConfiguration($this->secret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
            }
        };
    }
}
