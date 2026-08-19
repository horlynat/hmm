<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\IpBlockSubscriber;
use App\Repository\BlockedIpRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class IpBlockSubscriberTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string, 2: bool}>
     */
    public static function pathProvider(): iterable
    {
        yield 'login POST' => ['POST', '/login', true];
        yield 'login GET (page seule, pas une tentative)' => ['GET', '/login', false];
        yield '2fa check POST' => ['POST', '/2fa_check', true];
        yield 'api login check POST' => ['POST', '/api/login_check', true];
        yield 'api login check 2fa POST' => ['POST', '/api/login_check/2fa', true];
        yield 'unrelated path' => ['POST', '/admin/dashboard/', false];
        yield 'register is not a login attempt' => ['POST', '/register', false];
    }

    #[DataProvider('pathProvider')]
    public function testIsProtectedLoginAttempt(string $method, string $path, bool $expected): void
    {
        self::assertSame($expected, IpBlockSubscriber::isProtectedLoginAttempt($method, $path));
    }

    public function testIsProtectedLoginAttemptIsCaseInsensitiveOnMethod(): void
    {
        self::assertTrue(IpBlockSubscriber::isProtectedLoginAttempt('post', '/login'));
    }

    public function testAllowsRequestWhenIpNotBlocked(): void
    {
        $blockedIpRepository = $this->createStub(BlockedIpRepository::class);
        $blockedIpRepository->method('isBlocked')->willReturn(false);

        $subscriber = new IpBlockSubscriber($blockedIpRepository);
        $event = $this->createRequestEvent('POST', '/login', '203.0.113.1');

        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testDeniesRequestWhenIpBlocked(): void
    {
        $blockedIpRepository = $this->createStub(BlockedIpRepository::class);
        $blockedIpRepository->method('isBlocked')->willReturn(true);

        $subscriber = new IpBlockSubscriber($blockedIpRepository);
        $event = $this->createRequestEvent('POST', '/login', '203.0.113.1');

        $subscriber->onKernelRequest($event);

        self::assertTrue($event->hasResponse());
        self::assertSame(Response::HTTP_FORBIDDEN, $event->getResponse()->getStatusCode());
    }

    public function testIgnoresUnrelatedPathsEvenIfIpIsBlocked(): void
    {
        $blockedIpRepository = $this->createMock(BlockedIpRepository::class);
        $blockedIpRepository->expects($this->never())->method('isBlocked');

        $subscriber = new IpBlockSubscriber($blockedIpRepository);
        $event = $this->createRequestEvent('GET', '/admin/dashboard/', '203.0.113.1');

        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    private function createRequestEvent(string $method, string $path, string $ip): RequestEvent
    {
        $request = Request::create($path, $method);
        $request->server->set('REMOTE_ADDR', $ip);

        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
