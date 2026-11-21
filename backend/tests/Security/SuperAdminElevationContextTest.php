<?php

namespace App\Tests\Security;

use App\Security\SuperAdminElevationContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Contre-audit B2 (10e passe) : l'élévation super-admin n'est JAMAIS lue
 * depuis la session sur une route d'API — y compris quand le chemin est
 * encodé (`/%61pi/…`), que le pare-feu et le routeur décodent et traitent
 * comme /api.
 */
final class SuperAdminElevationContextTest extends TestCase
{
    private const USER = 'sa@example.test';

    protected function tearDown(): void
    {
        SuperAdminElevationContext::reset();
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function paths(): iterable
    {
        yield 'back-office web' => ['/admin/security/permissions/', true];
        yield 'API en clair' => ['/api/me', false];
        yield 'API, lettre encodée' => ['/%61pi/me', false];
        yield 'API, slash encodé' => ['/api%2Fme', false];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('paths')]
    public function testElevationMarkerIsReadOnlyOnWebRoutes(string $path, bool $expected): void
    {
        $until = time() + 600;
        $session = new Session(new MockArraySessionStorage());
        $session->set(SuperAdminElevationContext::SESSION_KEY, ['user' => self::USER, 'until' => $until]);

        $request = Request::create($path);
        $request->setSession($session);
        $request->cookies->set($session->getName(), $session->getId());

        SuperAdminElevationContext::bindFromRequest($request);

        self::assertSame($expected, SuperAdminElevationContext::allows(self::USER, (new \DateTimeImmutable())->setTimestamp($until)), $path);
    }
}
