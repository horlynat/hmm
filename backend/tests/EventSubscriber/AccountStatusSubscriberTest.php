<?php

namespace App\Tests\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\AccountStatusSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AccountStatusSubscriberTest extends TestCase
{
    private function createEvent(string $path): ControllerEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $controller = fn () => null;

        return new ControllerEvent($kernel, $controller, Request::create($path), HttpKernelInterface::MAIN_REQUEST);
    }

    private function createUser(bool $active, bool $verified, bool $totpEnabled = false): User
    {
        $user = new User();
        $user->setIsActive($active);
        $user->setIsVerified($verified);

        if ($totpEnabled) {
            $user->setTotpSecret('JBSWY3DPEHPK3PXP');
            $user->setIsTwoFactorEnabled(true);
        }

        return $user;
    }

    /**
     * @param string[] $roles
     */
    private function createSecurity(?User $user, array $roles): Security
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $attribute) => 'ROLE_EDITOR' === $attribute && in_array('ROLE_EDITOR', $roles, true),
        );

        return $security;
    }

    public function testOutOfScopePathIsIgnoredEvenForABlockedUser(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $subscriber = new AccountStatusSubscriber($this->createSecurity($this->createUser(false, true), ['ROLE_USER']), $urlGenerator);
        $event = $this->createEvent('/blog/some-article');
        $originalController = $event->getController();

        $subscriber->onKernelController($event);

        $this->assertSame($originalController, $event->getController());
    }

    #[DataProvider('skipListedPathProvider')]
    public function testSkipListedPathsAreNeverIntercepted(string $path): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $subscriber = new AccountStatusSubscriber($this->createSecurity($this->createUser(false, false), ['ROLE_EDITOR']), $urlGenerator);
        $event = $this->createEvent($path);
        $originalController = $event->getController();

        $subscriber->onKernelController($event);

        $this->assertSame($originalController, $event->getController());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function skipListedPathProvider(): iterable
    {
        yield 'admin blocked page itself' => ['/admin/compte-bloque'];
        yield 'profile blocked page itself' => ['/profile/compte-bloque'];
        yield '2fa login challenge' => ['/2fa'];
        yield '2fa self-service setup' => ['/profile/2fa/setup'];
        yield '2fa self-service index' => ['/profile/2fa'];
        yield 'email verification' => ['/verif/some-token'];
        yield 'resend verification' => ['/renvoiverif'];
    }

    public function testAnonymousUserIsIgnored(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $subscriber = new AccountStatusSubscriber($this->createSecurity(null, []), $urlGenerator);
        $event = $this->createEvent('/admin/dashboard');
        $originalController = $event->getController();

        $subscriber->onKernelController($event);

        $this->assertSame($originalController, $event->getController());
    }

    public function testActiveAndVerifiedUserIsLeftAlone(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $subscriber = new AccountStatusSubscriber($this->createSecurity($this->createUser(true, true), ['ROLE_EDITOR']), $urlGenerator);
        $event = $this->createEvent('/admin/dashboard');
        $originalController = $event->getController();

        $subscriber->onKernelController($event);

        $this->assertSame($originalController, $event->getController());
    }

    public function testDisabledAdminAreaUserIsRedirectedToAdminBlockedPage(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('admin_account_blocked', ['reason' => 'disabled'])
            ->willReturn('/admin/compte-bloque?reason=disabled');

        $subscriber = new AccountStatusSubscriber($this->createSecurity($this->createUser(false, true), ['ROLE_EDITOR']), $urlGenerator);
        $event = $this->createEvent('/admin/dashboard');

        $subscriber->onKernelController($event);

        $response = ($event->getController())();
        $this->assertSame('/admin/compte-bloque?reason=disabled', $response->headers->get('Location'));
    }

    public function testDisabledNonAdminUserIsRedirectedToProfileBlockedPage(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('profile_account_blocked', ['reason' => 'disabled'])
            ->willReturn('/profile/compte-bloque?reason=disabled');

        $subscriber = new AccountStatusSubscriber($this->createSecurity($this->createUser(false, true), ['ROLE_USER']), $urlGenerator);
        $event = $this->createEvent('/profile/5');

        $subscriber->onKernelController($event);

        $response = ($event->getController())();
        $this->assertSame('/profile/compte-bloque?reason=disabled', $response->headers->get('Location'));
    }

    public function testUnverifiedUserIsRedirectedWithUnverifiedReason(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('profile_account_blocked', ['reason' => 'unverified'])
            ->willReturn('/profile/compte-bloque?reason=unverified');

        $subscriber = new AccountStatusSubscriber($this->createSecurity($this->createUser(true, false), ['ROLE_USER']), $urlGenerator);
        $event = $this->createEvent('/projects/3');

        $subscriber->onKernelController($event);

        $response = ($event->getController())();
        $this->assertSame('/profile/compte-bloque?reason=unverified', $response->headers->get('Location'));
    }

    public function testUnverifiedUserOnMesDevisIsRedirected(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('profile_account_blocked', ['reason' => 'unverified'])
            ->willReturn('/profile/compte-bloque?reason=unverified');

        $subscriber = new AccountStatusSubscriber($this->createSecurity($this->createUser(true, false), ['ROLE_USER']), $urlGenerator);
        $event = $this->createEvent('/mes-devis');

        $subscriber->onKernelController($event);

        $response = ($event->getController())();
        $this->assertSame('/profile/compte-bloque?reason=unverified', $response->headers->get('Location'));
    }

    public function testActiveVerifiedClientWithout2faIsRedirectedToMandatorySetup(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('profile_two_factor_setup')
            ->willReturn('/profile/2fa/setup');

        $subscriber = new AccountStatusSubscriber($this->createSecurity($this->createUser(true, true), ['ROLE_USER']), $urlGenerator);
        $event = $this->createEvent('/projects/3');

        $subscriber->onKernelController($event);

        $response = ($event->getController())();
        $this->assertSame('/profile/2fa/setup', $response->headers->get('Location'));
    }

    public function testActiveVerifiedClientWith2faIsLeftAlone(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $subscriber = new AccountStatusSubscriber($this->createSecurity($this->createUser(true, true, totpEnabled: true), ['ROLE_USER']), $urlGenerator);
        $event = $this->createEvent('/profile/5');
        $originalController = $event->getController();

        $subscriber->onKernelController($event);

        $this->assertSame($originalController, $event->getController());
    }

    public function testActiveVerifiedAdminAreaUserWithout2faIsNotForcedIntoClientSetup(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        // ROLE_EDITOR (back-office) : la 2FA obligatoire ne concerne que
        // l'espace membre (client/collaborateur), pas le back-office.
        $subscriber = new AccountStatusSubscriber($this->createSecurity($this->createUser(true, true), ['ROLE_EDITOR']), $urlGenerator);
        $event = $this->createEvent('/admin/dashboard');
        $originalController = $event->getController();

        $subscriber->onKernelController($event);

        $this->assertSame($originalController, $event->getController());
    }
}
