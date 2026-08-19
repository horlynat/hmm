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
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

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
    public function testOnAuthenticationSuccessRejectsUnsafeCookieAndFallsBackToHome(string $unsafePath): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())->method('generate')->with('home_index')->willReturn('/');

        $authenticator = $this->createAuthenticator($urlGenerator);
        $request = Request::create('/login');
        $request->cookies->set('idle_return_to', $unsafePath);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getRoleNames')->willReturn(['ROLE_ADMIN']);

        $response = $authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertSame('/', $response->headers->get('Location'));
    }

    /**
     * @return iterable<string, array{0: array<string>}>
     */
    public static function roleProvider(): iterable
    {
        yield 'admin' => [['ROLE_ADMIN']];
        yield 'super admin' => [['ROLE_SUPER_ADMIN']];
        yield 'editor' => [['ROLE_EDITOR']];
        yield 'plain user' => [['ROLE_USER']];
    }

    /**
     * Sans cookie de retour valide, tout le monde atterrit sur la page
     * d'accueil (home_index) après connexion, quel que soit son rôle —
     * plus de bifurcation admin_dashboard_index / member_profile_read.
     *
     * @param array<string> $roles
     */
    #[DataProvider('roleProvider')]
    public function testOnAuthenticationSuccessFallsBackToHomeRegardlessOfRole(array $roles): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())->method('generate')->with('home_index')->willReturn('/');

        $authenticator = $this->createAuthenticator($urlGenerator);
        $request = Request::create('/login');

        $token = $this->createStub(TokenInterface::class);
        $token->method('getRoleNames')->willReturn($roles);

        $response = $authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertSame('/', $response->headers->get('Location'));
    }

    public function testAuthenticateRejectsServiceAccountAsIfUnknown(): void
    {
        // User::getRoles() ajoute ROLE_USER à TOUT compte, y compris un
        // compte de service — sans ce garde-fou dans le loader du UserBadge,
        // un compte ROLE_SERVICE pourrait obtenir une session web ici.
        $serviceAccount = (new User())->setEmail('svc@internal.local')->setIsSystemAccount(true);

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($serviceAccount);

        $limiter = new RateLimiterFactory(
            ['id' => 'login', 'policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 minute'],
            new InMemoryStorage(),
        );
        $authenticator = new SecurityAuthenticator($this->createStub(UrlGeneratorInterface::class), $userRepository, $limiter);

        $request = Request::create('/login', 'POST', ['email' => 'svc@internal.local', 'password' => 'whatever']);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $passport = $authenticator->authenticate($request);

        $this->expectException(UserNotFoundException::class);
        $passport->getBadge(UserBadge::class)->getUser();
    }

    public function testAuthenticateResolvesRegularUserNormally(): void
    {
        $regularUser = (new User())->setEmail('human@example.com');

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($regularUser);

        $limiter = new RateLimiterFactory(
            ['id' => 'login', 'policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 minute'],
            new InMemoryStorage(),
        );
        $authenticator = new SecurityAuthenticator($this->createStub(UrlGeneratorInterface::class), $userRepository, $limiter);

        $request = Request::create('/login', 'POST', ['email' => 'human@example.com', 'password' => 'whatever']);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $passport = $authenticator->authenticate($request);

        $this->assertSame($regularUser, $passport->getBadge(UserBadge::class)->getUser());
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
