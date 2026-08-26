<?php

namespace App\Controller;

use App\Entity\User;
use App\EventSubscriber\SpaceTrackerSubscriber;
use App\Security\TwoFactor\BackupCodeManager;
use App\Security\TwoFactor\PendingTotpUser;
use App\Service\AccountLinkResolver;
use App\Service\SecretEncryptor;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
    /** Codes de récupération en clair, stockés en session le temps d'un seul affichage. */
    private const RECOVERY_CODES_SESSION_KEY = 'two_factor_recovery_codes_once';

    public function __construct(
        private readonly AccountLinkResolver $accountLinkResolver,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('profile/two_factor/index.html.twig', [
            'user' => $this->getCurrentUser(),
            'mandatory' => $this->isTwoFactorMandatoryForCurrentUser(),
            'layout' => $this->layout(),
        ]);
    }

    #[Route('/setup', name: 'setup', methods: ['GET', 'POST'])]
    public function setup(
        Request $request,
        TotpAuthenticatorInterface $totpAuthenticator,
        EntityManagerInterface $entityManager,
        BackupCodeManager $backupCodeManager,
        SecretEncryptor $secretEncryptor,
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

        $pendingTotpUser = new PendingTotpUser($user->getTotpAuthenticationUsername(), $secret);
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
                    $user->setTotpSecret($secretEncryptor->encrypt($secret));
                    $user->setIsTwoFactorEnabled(true);
                    // Génère les codes de récupération dès l'activation : c'est le
                    // seul moment où ils seront affichés en clair.
                    $plainCodes = $backupCodeManager->generate($user);
                    $entityManager->flush();
                    $session->remove(self::SESSION_KEY);
                    $session->set(self::RECOVERY_CODES_SESSION_KEY, $plainCodes);

                    $this->addFlash('success', 'Double authentification activée. Conservez vos codes de récupération ci-dessous en lieu sûr.');

                    return $this->redirectToRoute('profile_two_factor_recovery_codes');
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
            'mandatory' => $this->isTwoFactorMandatoryForCurrentUser(),
            'layout' => $this->layout(),
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

        // La 2FA est obligatoire sur l'espace membre (cf. AccountStatusSubscriber,
        // qui redirige tout compte client/collaborateur non équipé vers l'activation)
        // — la désactiver soi-même viderait cette obligation de son sens. Seul un
        // admin peut la désactiver de force en cas de perte d'accès
        // (AdminSecurityTwoFactorController::disable(), procédure de secours
        // documentée là-bas).
        if ($this->isTwoFactorMandatoryForCurrentUser()) {
            $this->addFlash('error', "La double authentification est obligatoire sur votre compte et ne peut pas être désactivée depuis cette page. Si vous avez perdu l'accès à votre application d'authentification, contactez le support.");

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
                // Les codes de récupération ne doivent pas survivre à la 2FA.
                $user->setBackupCodes([]);
                $entityManager->flush();

                $this->addFlash('success', 'Double authentification désactivée.');

                return $this->redirectToRoute('profile_two_factor_index');
            }

            $error = 'Mot de passe incorrect.';
        }

        return $this->render('profile/two_factor/disable.html.twig', [
            'error' => $error,
            'layout' => $this->layout(),
        ]);
    }

    /**
     * Affiche les codes de récupération EN CLAIR une seule fois (juste après
     * leur génération). Les codes vivent uniquement en session et sont retirés
     * dès cet affichage : ils ne pourront plus jamais être revus ensuite.
     */
    #[Route('/recovery-codes', name: 'recovery_codes', methods: ['GET'])]
    public function recoveryCodes(Request $request, BackupCodeManager $backupCodeManager): Response
    {
        $user = $this->getCurrentUser();
        $session = $request->getSession();

        $codes = $session->get(self::RECOVERY_CODES_SESSION_KEY);
        $session->remove(self::RECOVERY_CODES_SESSION_KEY);

        if (!is_array($codes) || [] === $codes) {
            // Rien à afficher (accès direct, rechargement...) : on ne régénère
            // jamais silencieusement — l'utilisateur doit passer par le bouton dédié.
            $this->addFlash('info', "Les codes de récupération ne s'affichent qu'une seule fois. Régénérez-les si vous ne les avez pas notés.");

            return $this->redirectToRoute('profile_two_factor_index');
        }

        return $this->render('profile/two_factor/recovery_codes.html.twig', [
            'codes' => $codes,
            'remaining' => $backupCodeManager->countRemaining($user),
            'layout' => $this->layout(),
        ]);
    }

    /**
     * Régénère un nouveau lot de codes (invalide l'ancien). Protégé par CSRF et
     * confirmation du mot de passe, car un attaquant ayant une session ouverte
     * ne doit pas pouvoir se fabriquer de nouveaux codes d'accès silencieusement.
     */
    #[Route('/recovery-codes/regenerate', name: 'recovery_codes_regenerate', methods: ['POST'])]
    public function regenerateRecoveryCodes(
        Request $request,
        EntityManagerInterface $entityManager,
        BackupCodeManager $backupCodeManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $user = $this->getCurrentUser();

        if (!$user->isTotpAuthenticationEnabled()) {
            return $this->redirectToRoute('profile_two_factor_index');
        }

        if (!$this->isCsrfTokenValid('profile_two_factor_recovery_regenerate', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Merci de réessayer.');

            return $this->redirectToRoute('profile_two_factor_index');
        }

        if (!$passwordHasher->isPasswordValid($user, (string) $request->request->get('password', ''))) {
            $this->addFlash('error', 'Mot de passe incorrect. Codes de récupération inchangés.');

            return $this->redirectToRoute('profile_two_factor_index');
        }

        $plainCodes = $backupCodeManager->generate($user);
        $entityManager->flush();
        $request->getSession()->set(self::RECOVERY_CODES_SESSION_KEY, $plainCodes);

        $this->addFlash('success', 'Nouveaux codes de récupération générés. Les anciens ne sont plus valides.');

        return $this->redirectToRoute('profile_two_factor_recovery_codes');
    }

    /**
     * Même frontière que AccountStatusSubscriber : la 2FA est obligatoire pour
     * tout compte de l'espace membre (client/collaborateur, ROLE_USER sans
     * ROLE_EDITOR), jamais pour le back-office.
     */
    private function isTwoFactorMandatoryForCurrentUser(): bool
    {
        return !$this->isGranted('ROLE_EDITOR');
    }

    /**
     * Contrôleur unique et partagé (la logique 2FA ne diffère jamais selon le
     * rôle), mais le gabarit doit suivre le même partage que les profils
     * (AdminProfileController / MemberProfileController) : jamais l'aside
     * admin pour un compte de l'espace membre.
     *
     * Priorité au dernier espace effectivement parcouru dans cette session
     * (SpaceTrackerSubscriber) : un compte back-office qui vient de l'espace
     * membre (ex. /projects, pour tester le parcours collaborateur) doit y
     * rester en cliquant "Sécurité & 2FA" depuis ce menu-là, plutôt que de se
     * faire renvoyer l'aside admin au milieu de sa navigation. Sans espace
     * encore mémorisé (premier accès de la session, lien direct...), on
     * retombe sur le rôle du compte — comportement d'origine, et seul
     * comportement possible pour un vrai compte membre (jamais côté
     * back-office).
     */
    private function layout(): string
    {
        $space = $this->requestStack->getSession()->get(SpaceTrackerSubscriber::SESSION_KEY);

        $isBackOffice = match ($space) {
            SpaceTrackerSubscriber::SPACE_ADMIN => true,
            SpaceTrackerSubscriber::SPACE_MEMBER => false,
            default => $this->accountLinkResolver->isBackOfficeUser($this->getCurrentUser()),
        };

        return $isBackOffice ? 'base.html.twig' : 'member/base.html.twig';
    }

    private function getCurrentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
