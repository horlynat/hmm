<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Security\TwoFactor\BackupCodeManager;
use App\Security\TwoFactor\PendingTotpUser;
use App\Service\SecretEncryptor;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Activation de la 2FA (TOTP) en libre-service, contrepartie API (frontend
 * Next.js, /compte/securite) de TwoFactorController (web, /profile/2fa) —
 * même logique métier, exposée en JSON pour un firewall stateless.
 *
 * Pas de désactivation ici : la 2FA est obligatoire sur l'espace membre (cf.
 * AccountStatusSubscriber pour le pendant web ; TwoFactorAwareJwtSuccessHandler
 * pour la connexion API) et ne peut être désactivée que par un admin en cas de
 * perte d'accès (AdminSecurityTwoFactorController::disable()).
 *
 * Pas de #[IsGranted('ROLE_USER')] au niveau classe — même raison que
 * MeController : le firewall `api` est PUBLIC_ACCESS en access_control, un
 * IsGranted y lèverait une exception bruyante pour un cas parfaitement normal
 * (appel anonyme). 401 JSON propre à la place.
 *
 * Le secret TOTP n'est écrit sur l'entité qu'après vérification d'un code
 * valide (voir confirm()) — jamais avant, comme côté web.
 *
 * confirm() exige en plus la re-saisie du mot de passe : ce endpoint est
 * accessible via un Bearer token stateless (TTL 1 h) ; sans cette preuve, un
 * token intercepté suffirait à lier l'appareil TOTP d'un attaquant au compte
 * — une prise de contrôle qui survivrait à l'expiration du token. Même
 * exigence que MeController::update() pour le changement de mot de passe, et
 * que TwoFactorController (web) pour l'activation.
 */
#[Route('/api/me/2fa', name: 'api_me_two_factor_')]
final class ApiTwoFactorSetupController extends AbstractController
{
    #[Route('/setup', name: 'setup', methods: ['POST'])]
    public function setup(TotpAuthenticatorInterface $totpAuthenticator): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['detail' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isTotpAuthenticationEnabled()) {
            return $this->json(['detail' => 'La double authentification est déjà activée sur ce compte.'], Response::HTTP_CONFLICT);
        }

        $secret = $totpAuthenticator->generateSecret();
        $pendingTotpUser = new PendingTotpUser($user->getTotpAuthenticationUsername(), $secret);

        $qrCode = (new Builder(
            writer: new PngWriter(),
            data: $totpAuthenticator->getQRContent($pendingTotpUser),
            size: 240,
            margin: 8,
        ))->build();

        // Pas de session (firewall stateless) : le secret n'est stocké nulle
        // part côté serveur tant qu'il n'est pas confirmé — le client le
        // renvoie tel quel à confirm() ci-dessous. Sans risque : il s'agit du
        // compte de l'appelant lui-même (déjà authentifié par son propre
        // Bearer token), donc rien qu'il ne pourrait de toute façon choisir
        // lui-même ; confirm() ne fait que prouver qu'une vraie application
        // d'authentification est bien synchronisée sur CE secret avant de
        // l'écrire sur l'entité.
        return $this->json([
            'secret' => $secret,
            'qrCodeDataUri' => $qrCode->getDataUri(),
        ]);
    }

    #[Route('/confirm', name: 'confirm', methods: ['POST'])]
    public function confirm(
        Request $request,
        TotpAuthenticatorInterface $totpAuthenticator,
        EntityManagerInterface $entityManager,
        BackupCodeManager $backupCodeManager,
        SecretEncryptor $secretEncryptor,
        UserPasswordHasherInterface $passwordHasher,
        #[Autowire(service: 'limiter.two_factor_setup')]
        RateLimiterFactory $twoFactorSetupLimiter,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['detail' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isTotpAuthenticationEnabled()) {
            return $this->json(['detail' => 'La double authentification est déjà activée sur ce compte.'], Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true);
        $secret = is_array($data) && is_string($data['secret'] ?? null) ? $data['secret'] : '';
        $code = is_array($data) && is_string($data['code'] ?? null) ? trim((string) $data['code']) : '';
        $currentPassword = is_array($data) && is_string($data['currentPassword'] ?? null) ? $data['currentPassword'] : '';

        if ('' === $secret || '' === $code) {
            return $this->json(['detail' => 'Requête invalide : secret et code sont requis.'], Response::HTTP_BAD_REQUEST);
        }

        // Même limiter que le flux web (TwoFactorController::setup()) : un seul
        // budget de tentatives par compte, qu'il soit consommé depuis /profile/2fa
        // ou /api/me/2fa — pas deux compteurs indépendants à additionner.
        $limiter = $twoFactorSetupLimiter->create($user->getUserIdentifier());
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json(['detail' => 'Trop de tentatives. Patientez avant de réessayer.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Re-saisie du mot de passe : un Bearer token intercepté ne doit pas
        // suffire à lier un appareil TOTP tiers au compte (cf. docblock de classe).
        if ('' === $currentPassword || !$passwordHasher->isPasswordValid($user, $currentPassword)) {
            return $this->json(['detail' => 'Mot de passe actuel incorrect.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $pendingTotpUser = new PendingTotpUser($user->getTotpAuthenticationUsername(), $secret);
        if (!$totpAuthenticator->checkCode($pendingTotpUser, $code)) {
            return $this->json(['detail' => "Code invalide. Vérifiez l'heure de votre appareil et réessayez."], Response::HTTP_UNAUTHORIZED);
        }

        $user->setTotpSecret($secretEncryptor->encrypt($secret));
        $user->setIsTwoFactorEnabled(true);
        // Générés dès l'activation : c'est le seul moment où ils seront
        // renvoyés en clair — comme côté web, aucune procédure ne les
        // réaffiche ensuite (regenerate() en génère de nouveaux, jamais un
        // rappel des mêmes).
        $plainCodes = $backupCodeManager->generate($user);
        $entityManager->flush();

        return $this->json(['recoveryCodes' => $plainCodes]);
    }
}
