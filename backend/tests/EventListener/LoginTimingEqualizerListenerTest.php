<?php

namespace App\Tests\EventListener;

use App\EventListener\LoginTimingEqualizerListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Contre-audit B2 : un e-mail inconnu doit coûter une vérification de mot
 * de passe, comme un compte existant — sinon la durée de la réponse trahit
 * l'existence du compte.
 */
final class LoginTimingEqualizerListenerTest extends TestCase
{
    private int $verifies = 0;
    private int $hashes = 0;

    public function testUnknownEmailCostsOnePasswordVerification(): void
    {
        $listener = $this->listener();
        $listener->onLoginFailure($this->event(new BadCredentialsException('Bad credentials.', 0, new UserNotFoundException())));

        self::assertSame(1, $this->verifies);
    }

    public function testDummyHashIsComputedOnlyOnce(): void
    {
        $listener = $this->listener();
        for ($i = 0; $i < 3; ++$i) {
            $listener->onLoginFailure($this->event(new BadCredentialsException('Bad credentials.', 0, new UserNotFoundException())));
        }

        self::assertSame(3, $this->verifies);
        self::assertSame(1, $this->hashes);
    }

    public function testWrongPasswordOnAnExistingAccountIsNotHashedTwice(): void
    {
        $this->listener()->onLoginFailure($this->event(new BadCredentialsException('The presented password is invalid.')));

        self::assertSame(0, $this->verifies, 'Le mot de passe a déjà été vérifié par Symfony.');
    }

    public function testOtherFirewallsAreIgnored(): void
    {
        $this->listener()->onLoginFailure($this->event(new BadCredentialsException('x', 0, new UserNotFoundException()), 'oidc'));

        self::assertSame(0, $this->verifies);
    }

    private function listener(): LoginTimingEqualizerListener
    {
        $hasher = new class($this) implements PasswordHasherInterface {
            public function __construct(private readonly LoginTimingEqualizerListenerTest $test)
            {
            }

            public function hash(#[\SensitiveParameter] string $plainPassword): string
            {
                $this->test->countHash();

                return 'dummy';
            }

            public function verify(string $hashedPassword, #[\SensitiveParameter] string $plainPassword): bool
            {
                $this->test->countVerify();

                return false;
            }

            public function needsRehash(string $hashedPassword): bool
            {
                return false;
            }
        };

        $factory = $this->createStub(PasswordHasherFactoryInterface::class);
        $factory->method('getPasswordHasher')->willReturn($hasher);

        return new LoginTimingEqualizerListener($factory);
    }

    public function countHash(): void
    {
        ++$this->hashes;
    }

    public function countVerify(): void
    {
        ++$this->verifies;
    }

    private function event(AuthenticationException $exception, string $firewall = 'api_login'): LoginFailureEvent
    {
        $passport = new Passport(new UserBadge('nobody@test.invalid'), new PasswordCredentials('guess'));

        return new LoginFailureEvent($exception, $this->createStub(AuthenticatorInterface::class), new Request(), null, $firewall, $passport);
    }
}
