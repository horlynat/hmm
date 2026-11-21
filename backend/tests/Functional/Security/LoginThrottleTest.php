<?php

namespace App\Tests\Functional\Security;

use App\Entity\FailedLoginAttempt;
use App\Security\LoginThrottle;
use App\Tests\Functional\FunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * P1 #10 — délai progressif (e-mail, IP) appliqué avant le mot de passe,
 * journal tronqué jamais bloquant, absence d'oracle pendant un verrou.
 */
final class LoginThrottleTest extends FunctionalTestCase
{
    /**
     * @param array<string, string> $server
     */
    private function login(string $email, string $password, string $ip, array $server = []): Response
    {
        return $this->jsonRequest('POST', '/api/login_check', ['email' => $email, 'password' => $password], null, array_merge(['REMOTE_ADDR' => $ip], $server));
    }

    private static function ip(): string
    {
        return sprintf('172.%d.%d.%d', random_int(16, 31), random_int(0, 255), random_int(1, 254));
    }

    public function testDelayIsAppliedBeforePasswordCheckWithoutOracle(): void
    {
        $user = $this->createUser();
        $ip = self::ip();

        for ($i = 0; $i < LoginThrottle::PAIR_FREE_FAILURES; ++$i) {
            self::assertSame(401, $this->login($user->getEmail(), 'wrong'.$i, $ip)->getStatusCode());
        }

        $withGoodPassword = $this->login($user->getEmail(), self::PASSWORD, $ip);
        $withBadPassword = $this->login($user->getEmail(), 'wrong-again', $ip);

        self::assertSame(401, $withGoodPassword->getStatusCode(), 'Le bon mot de passe ne doit pas passer pendant le délai.');
        self::assertSame($withBadPassword->getStatusCode(), $withGoodPassword->getStatusCode());
        self::assertSame($withBadPassword->getContent(), $withGoodPassword->getContent(), 'Réponse différente selon le mot de passe : oracle.');
        self::assertStringNotContainsString('token', (string) $withGoodPassword->getContent());
    }

    /**
     * Contre-audit B2 (M1) : user.email est en collation insensible aux
     * accents — une variante accentuée charge le même compte et ne doit pas
     * repartir d'un compteur neuf.
     */
    public function testAccentVariantsShareTheSameCounter(): void
    {
        $user = $this->createUser(email: sprintf('victim-%s@test.invalid', bin2hex(random_bytes(4))));
        $ip = self::ip();

        for ($i = 0; $i < LoginThrottle::PAIR_FREE_FAILURES; ++$i) {
            self::assertSame(401, $this->login($user->getEmail(), 'wrong'.$i, $ip)->getStatusCode());
        }

        foreach (['vïctim', 'vîctim', 'VICTÎM', ' victim'] as $variant) {
            $email = str_replace('victim', $variant, $user->getEmail());
            $response = $this->login($email, self::PASSWORD, $ip);
            self::assertSame(401, $response->getStatusCode(), "La variante « $email » contourne le délai.");
            self::assertStringNotContainsString('token', (string) $response->getContent());
        }
    }

    public function testAccentVariantsOfAnUnknownEmailShareTheSameCounter(): void
    {
        $base = sprintf('nobody-%s@test.invalid', bin2hex(random_bytes(4)));
        $ip = self::ip();

        for ($i = 0; $i < LoginThrottle::PAIR_FREE_FAILURES; ++$i) {
            $this->login($base, 'wrong'.$i, $ip);
        }

        $first = $this->login(str_replace('nobody', 'nöbody', $base), 'x', $ip);
        $second = $this->login(str_replace('nobody', 'nòbody', $base), 'x', $ip);
        self::assertSame($first->getContent(), $second->getContent());
        self::assertSame(401, $first->getStatusCode());
    }

    public function testUnknownEmailIsThrottledExactlyLikeAKnownOne(): void
    {
        $known = $this->createUser();
        $unknown = sprintf('ghost-%s@test.invalid', bin2hex(random_bytes(5)));
        $ip = self::ip();

        $responses = [];
        foreach ([$known->getEmail(), $unknown] as $email) {
            for ($i = 0; $i <= LoginThrottle::PAIR_FREE_FAILURES; ++$i) {
                $last = $this->login($email, 'wrong'.$i, $ip);
            }
            $responses[] = $last->getContent();
        }

        self::assertSame($responses[0], $responses[1], 'Le délai ne doit pas révéler l\'existence du compte.');
    }

    public function testThrottleOnOneIpDoesNotLockTheOwnerOut(): void
    {
        $user = $this->createUser();
        $attackerIp = self::ip();

        for ($i = 0; $i < 6; ++$i) {
            $this->login($user->getEmail(), 'wrong'.$i, $attackerIp);
        }

        // Le titulaire, depuis une autre IP, se connecte normalement : plus de
        // verrou de compte global (ancien DoS ciblé).
        $response = $this->login($user->getEmail(), self::PASSWORD, self::ip());
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertArrayHasKey('token', $this->json($response));
        self::assertNull($this->reload($user)->getLockedUntil(), 'Aucun verrou dur ne doit plus être posé.');
    }

    public function testDistributedBruteForceIsSlowedButKnownIpsStillWork(): void
    {
        $user = $this->createUser();
        $homeIp = self::ip();
        self::assertSame(200, $this->login($user->getEmail(), self::PASSWORD, $homeIp)->getStatusCode());

        for ($i = 0; $i < LoginThrottle::GLOBAL_FREE_FAILURES; ++$i) {
            self::assertSame(401, $this->login($user->getEmail(), 'spray'.$i, self::ip())->getStatusCode());
        }

        $fromNewIp = $this->login($user->getEmail(), self::PASSWORD, self::ip());
        self::assertSame(401, $fromNewIp->getStatusCode(), 'Une nouvelle IP doit subir le délai global après une attaque distribuée.');

        $fromHome = $this->login($user->getEmail(), self::PASSWORD, $homeIp);
        self::assertSame(200, $fromHome->getStatusCode(), 'L\'IP habituelle du titulaire ne doit pas être bloquée.');
    }

    public function testOversizedInputsAreJournaledAndStillThrottled(): void
    {
        $user = $this->createUser();
        $ip = self::ip();
        $hugeUa = str_repeat('Mozilla/5.0 ', 600);

        for ($i = 0; $i < LoginThrottle::PAIR_FREE_FAILURES; ++$i) {
            self::assertSame(401, $this->login($user->getEmail(), 'wrong', $ip, ['HTTP_USER_AGENT' => $hugeUa])->getStatusCode());
        }
        self::assertSame(401, $this->login($user->getEmail(), self::PASSWORD, $ip, ['HTTP_USER_AGENT' => $hugeUa])->getStatusCode());

        $this->em()->clear();
        $attempts = $this->em()->getRepository(FailedLoginAttempt::class)->findBy(['ip' => $ip]);
        self::assertGreaterThanOrEqual(LoginThrottle::PAIR_FREE_FAILURES, \count($attempts), 'Les échecs doivent être journalisés malgré un User-Agent géant.');
        self::assertSame(255, mb_strlen((string) $attempts[0]->getUserAgent()));

        // E-mail tenté plus long que la colonne (180) : journal + pas de 500.
        $longEmail = str_repeat('a', 300).'@test.invalid';
        self::assertSame(401, $this->login($longEmail, 'x', self::ip(), ['HTTP_USER_AGENT' => $hugeUa])->getStatusCode());
    }

    public function testLongUserAgentDoesNotBreakSuccessfulLogin(): void
    {
        $user = $this->createUser();
        $response = $this->login($user->getEmail(), self::PASSWORD, self::ip(), ['HTTP_USER_AGENT' => str_repeat('X', 5000)]);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(255, mb_strlen((string) $this->reload($user)->getLastDevice()));
    }

    public function testLockedAccountAnswersLikeABadPassword(): void
    {
        $user = $this->createUser();
        $user->setLockedUntil(new \DateTimeImmutable('+15 minutes'));
        $this->em()->flush();

        $good = $this->login($user->getEmail(), self::PASSWORD, self::ip());
        $bad = $this->login($user->getEmail(), 'wrong', self::ip());

        self::assertSame(401, $good->getStatusCode());
        self::assertSame($bad->getContent(), $good->getContent());
    }

    public function testDelayGrowsAndIsCapped(): void
    {
        self::assertSame(0, LoginThrottle::delayFor(2, 3));
        self::assertSame(30, LoginThrottle::delayFor(3, 3));
        self::assertSame(60, LoginThrottle::delayFor(4, 3));
        self::assertSame(900, LoginThrottle::delayFor(50, 3));
    }
}
