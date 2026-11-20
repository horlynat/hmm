<?php

namespace App\Tests\Security;

use App\Exception\TooManyRequestsException;
use App\Security\ClientIpRateLimitKey;
use App\Security\NormalizedIpLoginRateLimiter;
use App\Service\PublicSubmissionThrottler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Limiteurs par IP comptés par préfixe IPv6 /64 (audit 2026-11-20, P1 #12).
 */
final class ClientIpRateLimitKeyTest extends TestCase
{
    /** @return iterable<string, array{0: ?string, 1: string}> */
    public static function keys(): iterable
    {
        yield 'IPv4 inchangée' => ['203.0.113.7', '203.0.113.7'];
        yield 'IPv6 tronquée au /64' => ['2001:db8:1:2:aaaa:bbbb:cccc:dddd', '2001:db8:1:2::'];
        yield 'IPv6 déjà réseau' => ['2001:db8:1:2::', '2001:db8:1:2::'];
        yield 'IPv6 forme non canonique' => ['2001:0DB8:0001:0002:0:0:0:1', '2001:db8:1:2::'];
        yield 'IPv4 encapsulée' => ['::ffff:203.0.113.7', '203.0.113.7'];
        yield 'loopback IPv6' => ['::1', '::'];
        yield 'absente' => [null, 'unknown'];
        yield 'vide' => ['', 'unknown'];
        yield 'invalide' => ['not-an-ip', 'unknown'];
        yield 'avec zone' => ['fe80::1%eth0', 'unknown'];
    }

    #[DataProvider('keys')]
    public function testKey(?string $ip, string $expected): void
    {
        self::assertSame($expected, ClientIpRateLimitKey::fromIp($ip));
    }

    /**
     * Attaque : un même hôte IPv6 qui change d'adresse dans son /64 à chaque
     * requête ne doit plus obtenir un compteur neuf.
     */
    public function testRotatingAddressesInsideOneSlash64ShareTheSameQuota(): void
    {
        $requestStack = new RequestStack();
        $factory = static fn (string $id, int $limit = 3): RateLimiterFactory => new RateLimiterFactory(
            ['id' => $id, 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '1 hour'],
            new InMemoryStorage(),
        );
        $throttler = new PublicSubmissionThrottler($factory('f'), $factory('r'), $factory('a'), $factory('s'), $factory('q'), $requestStack);

        for ($i = 1; $i <= 3; ++$i) {
            $requestStack->push(Request::create('/', server: ['REMOTE_ADDR' => sprintf('2001:db8:dead:beef::%x', random_int(1, 0xFFFF) + $i)]));
            $throttler->assertFormSubmissionAllowed();
        }

        // Autre /64 : quota indépendant.
        $requestStack->push(Request::create('/', server: ['REMOTE_ADDR' => '2001:db8:dead:bee0::1']));
        $throttler->assertFormSubmissionAllowed();

        $requestStack->push(Request::create('/', server: ['REMOTE_ADDR' => '2001:db8:dead:beef:1234:5678:9abc:def0']));
        $this->expectException(TooManyRequestsException::class);
        $throttler->assertFormSubmissionAllowed();
    }

    public function testLoginThrottlingDecoratorPassesTheNormalizedIp(): void
    {
        $inner = new class implements RequestRateLimiterInterface {
            /** @var string[] */
            public array $seen = [];

            public function consume(Request $request): RateLimit
            {
                $this->seen[] = (string) $request->getClientIp();

                return new RateLimit(1, new \DateTimeImmutable(), true, 5);
            }

            public function reset(Request $request): void
            {
                $this->seen[] = (string) $request->getClientIp();
            }
        };

        $limiter = new NormalizedIpLoginRateLimiter($inner);
        $request = Request::create('/api/login_check', 'POST', server: ['REMOTE_ADDR' => '2001:db8:1:2:3:4:5:6']);
        // Un en-tête de transfert forgé ne doit pas influencer la clé (proxy non approuvé ici).
        $request->headers->set('X-Forwarded-For', '198.51.100.1');

        $limiter->consume($request);
        $limiter->reset($request);

        self::assertSame(['2001:db8:1:2::', '2001:db8:1:2::'], $inner->seen);
        // La requête d'origine n'est pas modifiée.
        self::assertSame('2001:db8:1:2:3:4:5:6', $request->getClientIp());
    }
}
