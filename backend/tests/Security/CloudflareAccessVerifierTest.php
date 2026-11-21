<?php

namespace App\Tests\Security;

use App\Security\CloudflareAccessVerifier;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Contre-audit B2 : cache des clés Cloudflare Access — rotation de clés
 * absorbée sans attendre l'expiration du cache, relecture bornée face à des
 * `kid` aléatoires, échec de récupération mémorisé brièvement.
 */
final class CloudflareAccessVerifierTest extends TestCase
{
    private const TEAM = 'equipe.cloudflareaccess.com';
    private const AUD = 'aud-test';

    public function testKeyRotationIsPickedUpWithoutWaitingForTheCache(): void
    {
        $old = $this->newKey();
        $new = $this->newKey();
        $published = [$old];
        $calls = 0;
        $verifier = $this->verifier(function () use (&$published, &$calls): MockResponse {
            ++$calls;

            return new MockResponse(json_encode(['keys' => array_column($published, 'jwk')], \JSON_THROW_ON_ERROR));
        });

        self::assertTrue($verifier->verify($this->token($old)));
        $published = [$new]; // rotation chez Cloudflare

        self::assertTrue($verifier->verify($this->token($new)), 'La nouvelle clé doit être acceptée sans attendre 1 h.');
        self::assertSame(2, $calls);
    }

    public function testRandomKidsCannotForceRepeatedRefetches(): void
    {
        $real = $this->newKey();
        $calls = 0;
        $verifier = $this->verifier(function () use ($real, &$calls): MockResponse {
            ++$calls;

            return new MockResponse(json_encode(['keys' => [$real['jwk']]], \JSON_THROW_ON_ERROR));
        });

        self::assertTrue($verifier->verify($this->token($real)));
        for ($i = 0; $i < 20; ++$i) {
            self::assertFalse($verifier->verify($this->token($this->newKey())));
        }

        self::assertSame(2, $calls, 'Au plus une relecture par minute, quel que soit le nombre de kid inconnus.');
        self::assertTrue($verifier->verify($this->token($real)), 'La clé légitime reste acceptée.');
    }

    public function testFetchFailureIsCachedBrieflyAndRejects(): void
    {
        $calls = 0;
        $verifier = $this->verifier(function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse('down', ['http_code' => 503]);
        });

        $key = $this->newKey();
        self::assertFalse($verifier->verify($this->token($key)));
        self::assertFalse($verifier->verify($this->token($key)));
        self::assertFalse($verifier->verify($this->token($key)));

        self::assertSame(1, $calls, 'Un échec ne doit pas redéclencher un appel bloquant à chaque requête.');
    }

    public function testTokenWithoutKidNeverTriggersARefetch(): void
    {
        $real = $this->newKey();
        $calls = 0;
        $verifier = $this->verifier(function () use ($real, &$calls): MockResponse {
            ++$calls;

            return new MockResponse(json_encode(['keys' => [$real['jwk']]], \JSON_THROW_ON_ERROR));
        });

        $noKid = JWT::encode($this->claims(), $real['private'], 'RS256');
        self::assertFalse($verifier->verify($noKid));
        self::assertSame(1, $calls);
    }

    private function verifier(callable $responder): CloudflareAccessVerifier
    {
        return new CloudflareAccessVerifier(
            new MockHttpClient(static fn () => $responder()),
            new ArrayAdapter(),
            new NullLogger(),
            self::TEAM,
            self::AUD,
        );
    }

    /**
     * @return array{private: string, jwk: array<string, string>}
     */
    private function newKey(): array
    {
        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($res);
        openssl_pkey_export($res, $privatePem);
        $details = openssl_pkey_get_details($res);
        $b64 = static fn (string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

        return [
            'private' => $privatePem,
            'jwk' => [
                'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig',
                'kid' => 'kid-'.bin2hex(random_bytes(4)),
                'n' => $b64($details['rsa']['n']),
                'e' => $b64($details['rsa']['e']),
            ],
        ];
    }

    /**
     * @param array{private: string, jwk: array<string, string>} $key
     */
    private function token(array $key): string
    {
        return JWT::encode($this->claims(), $key['private'], 'RS256', $key['jwk']['kid']);
    }

    /**
     * @return array<string, mixed>
     */
    private function claims(): array
    {
        return ['iss' => 'https://'.self::TEAM, 'aud' => [self::AUD], 'exp' => time() + 300, 'iat' => time()];
    }
}
