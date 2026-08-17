<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\SecurityAuthenticator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class SecurityAuthenticatorTest extends TestCase
{
    private function createAuthenticator(UrlGeneratorInterface $urlGenerator): SecurityAuthenticator
    {
        $limiter = new RateLimiterFactory(
            ['id' => 'login', 'policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 minute'],
            new InMemoryStorage(),
        );

        return new SecurityAuthenticator($urlGenerator, $this->createStub(UserRepository::class), $limiter);
    }

    private function createUserWithId(int $id): User
    {
        $user = new User();
        $property = new \ReflectionProperty(User::class, 'id');
        $property->setValue($user, $id);

        return $user;
    }

    public function testStartRedirectsToLoginAndSetsReturnCookieForProtectedPath(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())->method('generate')->with('login')->willReturn('/login');

        $authenticator = $this->createAuthenticator($urlGenerator);
        $request = Request::create('/admin/foo?x=1');

        $response = $authenticator->start($request);

        $this->assertSame('/login', $response->headers->get('Location'));
        $cookie = $this->findCookie($response, 'idle_return_to');
        $this->assertNotNull($cookie);
        $this->assertSame('/admin/foo?x=1', $cookie->getValue());
    }

    public function testOnAuthenticationSuccessRedirectsToValidCookieTargetAndClearsIt(): void
    {
        $authenticator = $this->createAuthenticator($this->createStub(UrlGeneratorInterface::class));
        $request = Request::create('/login');
        $request->cookies->set('idle_return_to', '/admin/foo');

        $token = $this->createStub(TokenInterface::class);
        $token->method('getRoleNames')->willReturn(['ROLE_ADMIN']);

        $response = $authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertSame('/admin/foo', $response->headers->get('Location'));
        $cookie = $this->findCookie($response, 'idle_return_to');
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->getExpiresTime() < time());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeReturnPathProvider(): iterable
    {
        yield 'protocol-relative' => ['//evil.com'];
        yield 'absolute https' => ['https://evil.com'];
        yield 'backslash normalized by browsers to //' => ['/\\evil.com'];
        yield 'backslash variant' => ['/\\/evil.com'];
        yield 'embedded scheme' => ['/foo?x=javascript://evil.com'];
        yield 'tab control char' => ["/foo\tbar"];
        yield 'crlf injection' => ["/foo\r\nSet-Cookie: x=1"];
    }

    #[DataProvider('unsafeReturnPathProvider')]
    public function testOnAuthenticationSuccessRejectsUnsafeCookieAndFallsBackToAdminHome(string $unsafePath): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())->method('generate')->with('admin_home_index')->willReturn('/admin');

        $authenticator = $this->createAuthenticator($urlGenerator);
        $request = Request::create('/login');
        $request->cookies->set('idle_return_to', $unsafePath);

        $token = $this->createStub(TokenInterface::class);
        // Chaîne héritée complète (role_hierarchy) : un vrai
        // TokenInterface::getRoleNames() ne renvoie jamais que le seul rôle
        // directement attribué.
        $token->method('getRoleNames')->willReturn(['ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_MODERATOR', 'ROLE_EDITOR', 'ROLE_USER']);

        $response = $authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertSame('/admin', $response->headers->get('Location'));
    }

    /**
     * Régression : avant ce comportement, seul ROLE_ADMIN atterrissait
     * quelque part d'utile après connexion (admin_dashboard_index) —
     * Éditeur/Modérateur/Manager retombaient sur leur propre fiche profil,
     * sans aucun point d'entrée vers les espaces auxquels ils ont pourtant
     * accès (cf. AdminHomeController). ROLE_EDITOR est le vrai plancher
     * d'accès back-office (access_control ^/admin dans security.yaml), pas
     * ROLE_ADMIN.
     */
    /**
     * @param array<int, string> $roles
     */
    #[DataProvider('backofficeRoleProvider')]
    public function testOnAuthenticationSuccessRedirectsAnyBackofficeRoleToAdminHome(array $roles): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())->method('generate')->with('admin_home_index')->willReturn('/admin');

        $authenticator = $this->createAuthenticator($urlGenerator);
        $request = Request::create('/login');

        $token = $this->createStub(TokenInterface::class);
        $token->method('getRoleNames')->willReturn($roles);

        $response = $authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertSame('/admin', $response->headers->get('Location'));
    }

    /**
     * Rôles pleinement résolus (role_hierarchy de security.yaml) : un vrai
     * TokenInterface::getRoleNames() renvoie toute la chaîne héritée, pas
     * seulement le rôle directement attribué.
     *
     * @return iterable<string, array{0: array<int, string>}>
     */
    public static function backofficeRoleProvider(): iterable
    {
        yield 'Éditeur' => [['ROLE_EDITOR', 'ROLE_USER']];
        yield 'Modérateur' => [['ROLE_MODERATOR', 'ROLE_EDITOR', 'ROLE_USER']];
        yield 'Manager' => [['ROLE_MANAGER', 'ROLE_MODERATOR', 'ROLE_EDITOR', 'ROLE_USER']];
        yield 'Administrateur' => [['ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_MODERATOR', 'ROLE_EDITOR', 'ROLE_USER']];
        yield 'Super Administrateur' => [['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_MODERATOR', 'ROLE_EDITOR', 'ROLE_USER']];
    }

    /**
     * Un compte client (ROLE_USER seul, sans ROLE_EDITOR) n'a accès à rien
     * sous /admin (access_control) — s'il se connecte malgré tout via ce
     * formulaire, il atterrit sur sa propre fiche profil plutôt que sur un
     * hub back-office qui lui serait de toute façon inaccessible.
     */
    public function testOnAuthenticationSuccessFallsBackToProfileReadWhenNoBackofficeRole(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())->method('generate')->with('profile_read', ['id' => 42])->willReturn('/profile/42');

        $authenticator = $this->createAuthenticator($urlGenerator);
        $request = Request::create('/login');

        $token = $this->createStub(TokenInterface::class);
        $token->method('getRoleNames')->willReturn(['ROLE_USER']);
        $token->method('getUser')->willReturn($this->createUserWithId(42));

        $response = $authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertSame('/profile/42', $response->headers->get('Location'));
    }

    private function findCookie(Response $response, string $name): ?\Symfony\Component\HttpFoundation\Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        return null;
    }
}
