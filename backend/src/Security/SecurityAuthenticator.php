<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Le retour à la page consultée avant une déconnexion (pour inactivité ou
 * accès anonyme à une zone protégée) se fait via un cookie dédié plutôt que
 * via TargetPathTrait (session) : le trajet de déconnexion pour inactivité
 * passe par /logout, qui invalide la session (security.yaml) — tout ce qui
 * y serait stocké avant serait perdu. Le cookie, lui, est posé côté client
 * avant même la navigation vers /logout (voir assets/app.js), donc déjà
 * présent dans le navigateur indépendamment de la session serveur.
 */
class SecurityAuthenticator extends AbstractLoginFormAuthenticator
{
    public const LOGIN_ROUTE = 'login';
    private const IDLE_RETURN_TO_COOKIE = 'idle_return_to';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UserRepository $userRepository,
        #[Autowire(service: 'limiter.login')]
        private RateLimiterFactory $loginLimiter,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $email = trim((string) $request->getPayload()->getString('email'));
        $password = (string) $request->getPayload()->getString('password');

        if (empty($email) || empty($password)) {
            throw new BadCredentialsException('Email ou mot de passe manquant.');
        }

        $limiter = $this->loginLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new CustomUserMessageAuthenticationException('Trop de tentatives, réessayez plus tard.');
        }

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email, function (string $userIdentifier) {
                $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);

                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Utilisateur introuvable.');
                }
                if (!$user->isVerified()) {
                    throw new CustomUserMessageAuthenticationException('Votre compte n\'est pas encore vérifié.');
                }
                if (!$user->isActive()) {
                    throw new CustomUserMessageAuthenticationException('Votre compte a été désactivé.');
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

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $returnTo = $this->safeRelativePath($request->cookies->get(self::IDLE_RETURN_TO_COOKIE));
        if (null !== $returnTo) {
            $response = new RedirectResponse($returnTo);
            $response->headers->clearCookie(self::IDLE_RETURN_TO_COOKIE, '/');

            return $response;
        }

        if (in_array('ROLE_ADMIN', $token->getRoleNames(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('admin_dashboard_index'));
        }

        /** @var \App\Entity\User $user */
        $user = $token->getUser();

        return new RedirectResponse($this->urlGenerator->generate('profile_read', ['id' => $user->getId()]));
    }

    /**
     * Empêche la page consultée avant une déconnexion pour inactivité (voir
     * assets/app.js) de rester inaccessible une fois reconnecté : le
     * comportement de redirection vers /login lui-même n'est jamais modifié.
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $response = parent::start($request, $authException);

        $path = $this->safeRelativePath($request->getPathInfo().('' !== $request->getQueryString() ? '?'.$request->getQueryString() : ''));
        if (null !== $path && $response instanceof RedirectResponse) {
            $response->headers->setCookie(Cookie::create(
                self::IDLE_RETURN_TO_COOKIE,
                $path,
                time() + 300,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                Cookie::SAMESITE_STRICT,
            ));
        }

        return $response;
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

    /**
     * N'accepte qu'un chemin relatif à l'application — garde-fou anti
     * open-redirect, la valeur pouvant provenir d'un cookie posé côté client
     * (assets/app.js). Rejette : URL absolue ou "protocol-relative" `//...`,
     * tout backslash (les navigateurs normalisent `\` en `/`, donc
     * `/\evil.com` devient `//evil.com` une fois envoyé en en-tête Location),
     * et tout caractère de contrôle.
     */
    private function safeRelativePath(?string $path): ?string
    {
        if (null === $path || '' === $path) {
            return null;
        }

        if (str_contains($path, '\\') || str_contains($path, '://')) {
            return null;
        }

        if (1 !== preg_match('#^/(?!/)[^\r\n\t]*$#', $path)) {
            return null;
        }

        $parts = parse_url($path);

        return (false !== $parts && !isset($parts['scheme']) && !isset($parts['host'])) ? $path : null;
    }
}
