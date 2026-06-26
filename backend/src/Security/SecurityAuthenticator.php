<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use App\Repository\UserRepository;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class SecurityAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UserRepository $userRepository,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'limiter.login')]
        private RateLimiterFactory $loginLimiter
    ) {}


    /**
     * 🔒 Authentifie l'utilisateur à partir des données du formulaire.
     * - Vérifie email + mot de passe.
     * - Vérifie que le compte est validé.
     * - Applique une limite brute force via RateLimiter (par IP).
     * - Ajoute CSRF et RememberMe.
     */
    public function authenticate(Request $request): Passport
    {
        $email = trim((string) $request->getPayload()->getString('email'));
        $password = (string) $request->getPayload()->getString('password');

        if (empty($email) || empty($password)) {
            throw new BadCredentialsException('Email ou mot de passe manquant.');
        }

        // 🚨 Vérification brute force par IP
        // Chaque tentative consomme 1 "jeton". Si la limite est dépassée, on bloque.
        $limiter = $this->loginLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new CustomUserMessageAuthenticationException('Trop de tentatives, réessayez plus tard.');
        }

        // Sauvegarde du dernier email tenté en session (utile pour pré-remplir le champ login)
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        // Création du Passport avec :
        // - UserBadge : récupération de l’utilisateur par email
        // - PasswordCredentials : vérification du mot de passe
        // - Badges : CSRF + RememberMe
        return new Passport(
            new UserBadge($email, function ($userIdentifier) {
                $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);
                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Utilisateur introuvable.');
                }
                if (!$user->isVerified()) {
                    throw new CustomUserMessageAuthenticationException('Votre compte n’est pas encore vérifié.');
                }
                return $user;
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    /**
     * 🔒 Redirection après succès :
     * - Si une URL cible existe (page protégée), on y retourne.
     * - Sinon, on redirige selon le rôle de l’utilisateur.
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        if (in_array('ROLE_ADMIN', $token->getRoleNames(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('dashboard_index'));
        }

        return new RedirectResponse($this->urlGenerator->generate('dashboard_index'));
    }

    /**
     * 🔒 URL de connexion par défaut.
     */
    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
