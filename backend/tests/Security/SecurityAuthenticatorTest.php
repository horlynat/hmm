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
    public function testOnAuthenticationSuccessRejectsUnsafeCookieAndFallsBackToAdminDashboard(string $unsafePath): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())->method('generate')->with('admin_dashboard_index')->willReturn('/admin/dashboard');

        $authenticator = $this->createAuthenticator($urlGenerator);
        $request = Request::create('/login');
        $request->cookies->set('idle_return_to', $unsafePath);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getRoleNames')->willReturn(['ROLE_ADMIN']);

        $response = $authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertSame('/admin/dashboard', $response->headers->get('Location'));
    }

    public function testOnAuthenticationSuccessFallsBackToProfileReadForNonAdmin(): void
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
