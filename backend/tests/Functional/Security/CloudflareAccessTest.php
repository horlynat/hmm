<?php

namespace App\Tests\Functional\Security;

use App\Security\CloudflareAccessVerifier;
use App\Tests\Functional\FunctionalTestCase;
use Firebase\JWT\JWT;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * P1 #15 — vérification optionnelle de Cf-Access-Jwt-Assertion sur l'hôte
 * back-office (wip — hors du lot priorisé P0 #3 / P0 #4).
 *
 * .env.test : CF_ACCESS_HOSTS=dark.test.invalid, CF_ACCESS_TEAM_DOMAIN=team.test.invalid,
 * CF_ACCESS_AUD=test-aud-1234.
 */
final class CloudflareAccessTest extends FunctionalTestCase
{
    /** @var array{private: string, jwks: array<string, mixed>, kid: string} */
    private array $cfKey;

    private function installCloudflareVerifier(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $privatePem);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        $kid = 'kid-'.bin2hex(random_bytes(4));
        $b64 = static fn (string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
        $this->cfKey = [
            'private' => $privatePem,
            'kid' => $kid,
            'jwks' => ['keys' => [[
                'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => $kid,
                'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e']),
            ]]],
        ];

        $http = new MockHttpClient(function (string $method, string $url): MockResponse {
            self::assertSame('https://team.test.invalid/cdn-cgi/access/certs', $url);

            return new MockResponse(json_encode($this->cfKey['jwks'], \JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);
        });

        static::getContainer()->set(CloudflareAccessVerifier::class, new CloudflareAccessVerifier(
            $http,
            new ArrayAdapter(),
            new NullLogger(),
            'team.test.invalid',
            'test-aud-1234',
        ));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function cfToken(array $overrides = [], ?string $privateKey = null, string $alg = 'RS256'): string
    {
        $claims = array_merge([
            'iss' => 'https://team.test.invalid',
            'aud' => ['test-aud-1234'],
            'sub' => 'someone',
            'email' => 'admin@example.test',
            'iat' => time(),
            'nbf' => time() - 5,
            'exp' => time() + 300,
        ], $overrides);

        return JWT::encode($claims, $privateKey ?? $this->cfKey['private'], $alg, $this->cfKey['kid']);
    }

    private function requestDark(?string $assertion): int
    {
        $server = null === $assertion ? [] : ['HTTP_CF_ACCESS_JWT_ASSERTION' => $assertion];
        $this->client->request('GET', 'http://dark.test.invalid/login', server: $server);

        return $this->client->getResponse()->getStatusCode();
    }

    public function testCloudflareAccessAcceptsGenuineAssertion(): void
    {
        $this->installCloudflareVerifier();
        self::assertSame(200, $this->requestDark($this->cfToken()));
    }

    public function testCloudflareAccessRejectsForgedAssertions(): void
    {
        $this->installCloudflareVerifier();

        self::assertSame(403, $this->requestDark(null), 'sans en-tête');
        self::assertSame(403, $this->requestDark(''), 'en-tête vide');
        self::assertSame(403, $this->requestDark('not.a.jwt'), 'charabia');
        self::assertSame(403, $this->requestDark($this->cfToken(['aud' => ['other-app']])), 'autre audience');
        self::assertSame(403, $this->requestDark($this->cfToken(['iss' => 'https://evil.cloudflareaccess.com'])), 'autre émetteur');
        self::assertSame(403, $this->requestDark($this->cfToken(['exp' => time() - 3600, 'iat' => time() - 7200, 'nbf' => time() - 7200])), 'expiré');
        self::assertSame(403, $this->requestDark($this->cfToken(['nbf' => time() + 3600])), 'pas encore valide');

        // Signé par une autre clé, même kid.
        $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($other);
        openssl_pkey_export($other, $otherPem);
        self::assertSame(403, $this->requestDark($this->cfToken([], $otherPem)), 'mauvaise clé');

        // Confusion d'algorithme : HS256 avec la clé publique comme secret.
        $publicPem = openssl_pkey_get_details(openssl_pkey_get_private($this->cfKey['private']))['key'] ?? '';
        self::assertSame(403, $this->requestDark($this->cfToken([], $publicPem, 'HS256')), 'HS256 / clé publique');

        // alg=none.
        $header = rtrim(strtr(base64_encode('{"alg":"none","typ":"JWT"}'), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode((string) json_encode(['iss' => 'https://team.test.invalid', 'aud' => 'test-aud-1234', 'exp' => time() + 300])), '+/', '-_'), '=');
        self::assertSame(403, $this->requestDark($header.'.'.$payload.'.'), 'alg none');
    }

    /**
     * Contre-audit B2 (M3) : un hôte protégé dont la configuration du
     * vérificateur est incomplète ne doit pas s'ouvrir en silence.
     */
    public function testIncompleteConfigurationFailsClosed(): void
    {
        static::getContainer()->set(CloudflareAccessVerifier::class, new CloudflareAccessVerifier(
            new MockHttpClient(static fn (): MockResponse => self::fail('Aucun appel réseau attendu.')),
            new ArrayAdapter(),
            new NullLogger(),
            '',
            '',
        ));

        self::assertSame(403, $this->requestDark(null), 'sans en-tête');
        self::assertSame(403, $this->requestDark('a.b.c'), 'avec un en-tête quelconque');
    }

    /**
     * Contre-audit B2 (M3) : les noms internes joignables sans Cloudflare
     * (`hmm-backend`, `backend`) ne servent que /api, /uploads, /newsletter —
     * jamais le back-office, qui ne reste servi que par l'hôte protégé.
     *
     * @return iterable<string, array{string}>
     */
    public static function internalHosts(): iterable
    {
        yield 'hmm-backend' => ['hmm-backend'];
        yield 'backend' => ['backend'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('internalHosts')]
    public function testInternalHostsNeverServeTheBackOffice(string $host): void
    {
        foreach (['/login', '/admin', '/admin/users', '/profile/2fa'] as $path) {
            $this->client->request('GET', 'http://'.$host.$path);
            self::assertSame(404, $this->client->getResponse()->getStatusCode(), $host.$path);
        }

        $this->client->request('GET', 'http://'.$host.'/api/home_contents', server: ['HTTP_ACCEPT' => 'application/ld+json']);
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), $host.'/api/home_contents');
    }

    public function testCloudflareAccessDoesNotApplyToOtherHosts(): void
    {
        $this->installCloudflareVerifier();
        $this->client->request('GET', 'http://localhost/login');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }
}
