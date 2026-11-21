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

        // Une seule clé d'attaquant, kid différent à chaque jeton : générer une
        // clé RSA par tour rendait la boucle assez lente (suite chargée) pour
        // dépasser la minute — une 2e relecture devenait alors légitime et le
        // test échouait sans défaut réel. La durée ne dépend plus de la machine.
        $attacker = $this->newKey();
        $start = time();
        self::assertTrue($verifier->verify($this->token($real)));
        for ($i = 0; $i < 20; ++$i) {
            $jwk = $attacker['jwk'];
            $jwk['kid'] = 'kid-'.bin2hex(random_bytes(6));
            self::assertFalse($verifier->verify($this->token(['private' => $attacker['private'], 'jwk' => $jwk])));
        }
        self::assertLessThan(60, time() - $start, 'Le scénario doit tenir dans la fenêtre d\'une minute.');

        self::assertSame(2, $calls, 'Au plus une relecture par minute, quel que soit le nombre de kid inconnus.');
        self::assertTrue($verifier->verify($this->token($real)), 'La clé légitime reste acceptée.');
    }

    /**
     * Contre-audit B2 (3e passe, 2a) : jetons préparés À L'AVANCE puis envoyés
     * en rafale (même seconde) — c'est le scénario qui contournait la limite.
     */
    public function testBurstOfRandomKidsInTheSameSecondTriggersOneRefetchAtMost(): void
    {
        $real = $this->newKey();
        $calls = 0;
        $verifier = $this->verifier(function () use ($real, &$calls): MockResponse {
            ++$calls;

            return new MockResponse(json_encode(['keys' => [$real['jwk']]], \JSON_THROW_ON_ERROR));
        });

        $attacker = $this->newKey();
        $tokens = [];
        for ($i = 0; $i < 20; ++$i) {
            $jwk = $attacker['jwk'];
            $jwk['kid'] = 'kid-'.bin2hex(random_bytes(6));
            $tokens[] = $this->token(['private' => $attacker['private'], 'jwk' => $jwk]);
        }

        self::assertTrue($verifier->verify($this->token($real)));
        foreach ($tokens as $token) {
            self::assertFalse($verifier->verify($token));
        }

        self::assertSame(2, $calls, '20 kid aléatoires dans la même seconde ne doivent déclencher qu\'une relecture.');
    }

    /**
     * Contre-audit B2 (3e passe, 2b) : pendant une panne de Cloudflare, un kid
     * aléatoire ne doit pas faire perdre les clés valides déjà en cache.
     */
    public function testRandomKidDuringAnOutageKeepsTheValidKeys(): void
    {
        $real = $this->newKey();
        $cloudflare = new \ArrayObject(['up' => true]);
        $verifier = $this->verifier(function () use ($real, $cloudflare): MockResponse {
            return true === $cloudflare['up']
                ? new MockResponse(json_encode(['keys' => [$real['jwk']]], \JSON_THROW_ON_ERROR))
                : new MockResponse('down', ['http_code' => 503]);
        });

        self::assertTrue($verifier->verify($this->token($real)));
        $cloudflare['up'] = false; // panne de l'adresse des certificats

        self::assertFalse($verifier->verify($this->token($this->newKey())));
        self::assertTrue($verifier->verify($this->token($real)), 'Le jeton légitime doit rester accepté pendant la panne.');
    }

    /**
     * Contre-audit B2 (5e passe, C2) : une clé malformée mêlée aux bonnes ne
     * doit ni remplacer le cache par un jeu inutilisable, ni faire refuser
     * les jetons légitimes.
     */
    public function testMalformedKeyMixedWithGoodOnesIsIgnored(): void
    {
        $real = $this->newKey();
        $verifier = $this->verifier(static fn (): MockResponse => new MockResponse(json_encode(['keys' => [
            ['kid' => 'broken', 'use' => 'sig'], // sans kty
            ['kty' => 'RSA', 'kid' => 'half', 'n' => 'AQAB'], // sans e
            $real['jwk'],
        ]], \JSON_THROW_ON_ERROR)));

        self::assertTrue($verifier->verify($this->token($real)));
    }

    public function testInvalidKeySetDuringARefreshKeepsTheValidKeys(): void
    {
        $real = $this->newKey();
        $cloudflare = new \ArrayObject(['broken' => false]);
        $verifier = $this->verifier(function () use ($real, $cloudflare): MockResponse {
            $keys = true === $cloudflare['broken'] ? [['kid' => 'x', 'use' => 'sig']] : [$real['jwk']];

            return new MockResponse(json_encode(['keys' => $keys], \JSON_THROW_ON_ERROR));
        });

        self::assertTrue($verifier->verify($this->token($real)));
        $cloudflare['broken'] = true; // Cloudflare sert un jeu inutilisable

        self::assertFalse($verifier->verify($this->token($this->newKey())), 'kid inconnu -> relecture (jeu invalide)');
        self::assertTrue($verifier->verify($this->token($real)), 'Les clés valides doivent rester en cache.');
    }

    /**
     * Contre-audit B2 (5e passe, C2) : `parseKeySet(…, 'RS256')` n'impose
     * RS256 qu'aux clés SANS `alg` — une clé symétrique déclarée HS256 dans
     * le JWKS faisait accepter un jeton HS256.
     */
    public function testSymmetricKeyInTheKeySetIsNeverAccepted(): void
    {
        $secret = random_bytes(32);
        $b64 = static fn (string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
        $verifier = $this->verifier(static fn (): MockResponse => new MockResponse(json_encode(['keys' => [
            ['kty' => 'oct', 'alg' => 'HS256', 'kid' => 'sym', 'k' => $b64($secret)],
        ]], \JSON_THROW_ON_ERROR)));

        $hs256 = JWT::encode($this->claims(), $secret, 'HS256', 'sym');
        self::assertFalse($verifier->verify($hs256));
    }

    /**
     * Contre-audit B2 (7e passe, C6) : verrou de relecture inaccessible ->
     * avertissement visible, émis une seule fois même sous une rafale de kid
     * aléatoires ; la clé déjà en cache reste acceptée.
     */
    public function testUnreachableLockIsLoggedOnceAndKeepsCachedKeys(): void
    {
        $real = $this->newKey();
        $logger = new class extends \Psr\Log\AbstractLogger {
            /** @var list<string> */
            public array $warnings = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                if ('warning' === $level && str_contains((string) $message, 'verrou')) {
                    $this->warnings[] = (string) $message;
                }
            }
        };
        $reflection = new \ReflectionProperty(CloudflareAccessVerifier::class, 'lastLockWarningAt');
        $reflection->setValue(null, 0);

        $verifier = new CloudflareAccessVerifier(
            new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode(['keys' => [$real['jwk']]], \JSON_THROW_ON_ERROR))),
            new ArrayAdapter(),
            $logger,
            self::TEAM,
            self::AUD,
            '/nonexistent-'.bin2hex(random_bytes(4)),
        );

        self::assertTrue($verifier->verify($this->token($real)));
        $attacker = $this->newKey();
        for ($i = 0; $i < 10; ++$i) {
            $jwk = $attacker['jwk'];
            $jwk['kid'] = 'kid-'.bin2hex(random_bytes(6));
            self::assertFalse($verifier->verify($this->token(['private' => $attacker['private'], 'jwk' => $jwk])));
        }

        self::assertCount(1, $logger->warnings, 'Un seul avertissement par période, pas un par requête.');
        self::assertTrue($verifier->verify($this->token($real)));

        // 5 minutes plus tard (simulé), le problème toujours présent doit être
        // signalé à nouveau — il ne disparaît pas des logs jusqu'au redémarrage.
        $reflection->setValue(null, time() - 301);
        $jwk = $attacker['jwk'];
        $jwk['kid'] = 'kid-'.bin2hex(random_bytes(6));
        self::assertFalse($verifier->verify($this->token(['private' => $attacker['private'], 'jwk' => $jwk])));
        self::assertCount(2, $logger->warnings, 'Le problème durable doit être signalé à nouveau après 5 minutes.');
        $reflection->setValue(null, 0);
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
