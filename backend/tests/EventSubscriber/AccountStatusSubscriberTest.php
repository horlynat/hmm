<?php

namespace App\Tests\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\AccountStatusSubscriber;
use App\Security\TwoFactorMandatePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

final class AccountStatusSubscriberTest extends TestCase
{
    private function createEvent(string $path): ControllerEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $controller = fn () => null;

        return new ControllerEvent($kernel, $controller, Request::create($path), HttpKernelInterface::MAIN_REQUEST);
    }

    /**
     * @param list<string> $roles Rôles BRUTS de l'entité (gouvernent App\Security\
     *                            TwoFactorMandatePolicy, utilisée réellement ici,
     *                            pas mockée — distincts de $editorArea ci-dessous).
     */
    private function createUser(bool $active, bool $verified, array $roles = [], bool $totpEnabled = false): User
    {
        $user = new User();
        $user->setIsActive($active);
        $user->setIsVerified($verified);
        $user->setRoles($roles);

        if ($totpEnabled) {
            $user->setTotpSecret('JBSWY3DPEHPK3PXP');
            $user->setIsTwoFactorEnabled(true);
        }

        return $user;
    }

    /**
     * $editorArea pilote UNIQUEMENT le choix de la page "compte bloqué"
     * (admin_account_blocked vs profile_account_blocked) — reflète
     * Security::isGranted('ROLE_EDITOR'), indépendant des rôles réels de
     * l'entité utilisés par TwoFactorMandatePolicy.
     */
    private function createSecurity(?User $user, bool $editorArea): Security
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $attribute) => 'ROLE_EDITOR' === $attribute && $editorArea,
        );

        return $security;
    }

    private function createMandatePolicy(): TwoFactorMandatePolicy
    {
        return new TwoFactorMandatePolicy(new RoleHierarchy([
            'ROLE_EDITOR' => ['ROLE_USER'],
            'ROLE_MODERATOR' => ['ROLE_EDITOR'],
            'ROLE_MANAGER' => ['ROLE_MODERATOR'],
            'ROLE_ADMIN' => ['ROLE_MANAGER'],
            'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN'],
        ]));
    }

    private function subscriber(Security $security): AccountStatusSubscriber
    {
        // Stub (pas de mock) : ces tests ne vérifient jamais d'appel à generate(),
        // seulement l'absence d'interception du contrôleur.
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        return new AccountStatusSubscriber($security, $urlGenerator, $this->createMandatePolicy());
    }

    public function testOutOfScopePathIsIgnoredEvenForABlockedUser(): void
    {
        $subscriber = $this->subscriber($this->createSecurity($this->createUser(false, true, ['ROLE_USER']), editorArea: false));
        $event = $this->createEvent('/blog/some-article');
        $originalController = $event->getController();

        $subscriber->onKernelController($event);

        $this->assertSame($originalController, $event->getController());
    }

    #[DataProvider('skipListedPathProvider')]
    public function testSkipListedPathsAreNeverIntercepted(string $path): void
    {
        $subscriber = $this->subscriber($this->createSecurity($this->createUser(false, false, ['ROLE_EDITOR']), editorArea: true));
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
        $subscriber = $this->subscriber($this->createSecurity(null, editorArea: false));
        $event = $this->createEvent('/admin/dashboard');
        $originalController = $event->getController();

        $subscriber->onKernelController($event);

        $this->assertSame($originalController, $event->getController());
    }

    public function testActiveAndVerifiedEditorWith2faIsLeftAlone(): void
    {
        $subscriber = $this->subscriber($this->createSecurity($this->createUser(true, true, ['ROLE_EDITOR'], totpEnabled: true), editorArea: true));
        $event = $this->createEvent('/admin/dashboard');
        $originalController = $event->getController();

        $subscriber->onKernelController($event);

        $this->assertSame($originalController, $event->getController());
    }

    public function testDisabledAdminAreaUserIsRedirectedToAdminBlockedPage(): void
    {
        $security = $this->createSecurity($this->createUser(false, true, ['ROLE_EDITOR']), editorArea: true);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('admin_account_blocked', ['reason' => 'disabled'])
            ->willReturn('/admin/compte-bloque?reason=disabled');

        $subscriber = new AccountStatusSubscriber($security, $urlGenerator, $this->createMandatePolicy());
        $event = $this->createEvent('/admin/dashboard');

        $subscriber->onKernelController($event);

        $response = ($event->getController())();
        $this->assertSame('/admin/compte-bloque?reason=disabled', $response->headers->get('Location'));
    }

    /**
     * Contre-audit B2 (11e passe) : `/%61dmin/…` est servi par le routeur
     * comme /admin/… — la redirection « compte bloqué » doit s'appliquer.
     */
    public function testEncodedAdminPathIsStillInScope(): void
    {
        $security = $this->createSecurity($this->createUser(false, true, ['ROLE_EDITOR']), editorArea: true);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('admin_account_blocked', ['reason' => 'disabled'])
            ->willReturn('/admin/compte-bloque?reason=disabled');

        $subscriber = new AccountStatusSubscriber($security, $urlGenerator, $this->createMandatePolicy());
        $event = $this->createEvent('/%61dmin/dashboard');

        $subscriber->onKernelController($event);

        $response = ($event->getController())();
        $this->assertSame('/admin/compte-bloque?reason=disabled', $response->headers->get('Location'));
    }

    public function testDisabledNonAdminUserIsRedirectedToProfileBlockedPage(): void
    {
        $security = $this->createSecurity($this->createUser(false, true, ['ROLE_USER']), editorArea: false);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('profile_account_blocked', ['reason' => 'disabled'])
            ->willReturn('/profile/compte-bloque?reason=disabled');

        $subscriber = new AccountStatusSubscriber($security, $urlGenerator, $this->createMandatePolicy());
        $event = $this->createEvent('/profile/5');

        $subscriber->onKernelController($event);

        $response = ($event->getController())();
        $this->assertSame('/profile/compte-bloque?reason=disabled', $response->headers->get('Location'));
    }

    public function testUnverifiedUserIsRedirectedWithUnverifiedReason(): void
    {
        $security = $this->createSecurity($this->createUser(true, false, ['ROLE_USER']), editorArea: false);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('profile_account_blocked', ['reason' => 'unverified'])
            ->willReturn('/profile/compte-bloque?reason=unverified');

        $subscriber = new AccountStatusSubscriber($security, $urlGenerator, $this->createMandatePolicy());
        $event = $this->createEvent('/projects/3');

        $subscriber->onKernelController($event);

        $response = ($event->getController())();
        $this->assertSame('/profile/compte-bloque?reason=unverified', $response->headers->get('Location'));
    }

    public function testUnverifiedUserOnMesDevisIsRedirected(): void
    {
        $security = $this->createSecurity($this->createUser(true, false, ['ROLE_USER']), editorArea: false);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('profile_account_blocked', ['reason' => 'unverified'])
            ->willReturn('/profile/compte-bloque?reason=unverified');

        $subscriber = new AccountStatusSubscriber($security, $urlGenerator, $this->createMandatePolicy());
        $event = $this->createEvent('/mes-devis');

        $subscriber->onKernelController($event);

        $response = ($event->getController())();
        $this->assertSame('/profile/compte-bloque?reason=unverified', $response->headers->get('Location'));
    }

    public function testUnverifiedUserOnMemberProfilIsRedirected(): void
    {
        // /user/profil est dans le périmètre depuis P1 #13 (c) — oublié
        // avant ce correctif (constat de l'audit).
        $security = $this->createSecurity($this->createUser(true, false, ['ROLE_USER']), editorArea: false);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('profile_account_blocked', ['reason' => 'unverified'])
            ->willReturn('/profile/compte-bloque?reason=unverified');

        $subscriber = new AccountStatusSubscriber($security, $urlGenerator, $this->createMandatePolicy());
        $event = $this->createEvent('/user/profil');

        $subscriber->onKernelController($event);

        $response = ($event->getController())();
        $this->assertSame('/profile/compte-bloque?reason=unverified', $response->headers->get('Location'));
    }

    public function testActiveVerifiedClientWithout2faIsRedirectedToMandatorySetup(): void
    {
        $security = $this->createSecurity($this->createUser(true, true, ['ROLE_USER']), editorArea: false);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('profile_two_factor_setup')
            ->willReturn('/profile/2fa/setup');

        $subscriber = new AccountStatusSubscriber($security, $urlGenerator, $this->createMandatePolicy());
        $event = $this->createEvent('/projects/3');

        $subscriber->onKernelController($event);

        $response = ($event->getController())();
        $this->assertSame('/profile/2fa/setup', $response->headers->get('Location'));
    }

    public function testActiveVerifiedClientWith2faIsLeftAlone(): void
    {
        $subscriber = $this->subscriber($this->createSecurity($this->createUser(true, true, ['ROLE_USER'], totpEnabled: true), editorArea: false));
        $event = $this->createEvent('/profile/5');
        $originalController = $event->getController();

        $subscriber->onKernelController($event);

        $this->assertSame($originalController, $event->getController());
    }

    public function testEditorModeratorManagerAreaUserWithout2faIsNotForcedIntoSetup(): void
    {
        // P1 #13 : la 2FA obligatoire couvre désormais le back-office
        // ROLE_ADMIN+, mais PAS les rangs intermédiaires (EDITOR/MODERATOR/
        // MANAGER), toujours hors périmètre de la demande initiale.
        foreach (['ROLE_EDITOR', 'ROLE_MODERATOR', 'ROLE_MANAGER'] as $role) {
            $subscriber = $this->subscriber($this->createSecurity($this->createUser(true, true, [$role]), editorArea: true));
            $event = $this->createEvent('/admin/dashboard');
            $originalController = $event->getController();

            $subscriber->onKernelController($event);

            $this->assertSame($originalController, $event->getController(), $role);
        }
    }

    public function testAdminAreaUserWithout2faIsNowForcedIntoSetup(): void
    {
        $security = $this->createSecurity($this->createUser(true, true, ['ROLE_ADMIN']), editorArea: true);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('profile_two_factor_setup')
            ->willReturn('/profile/2fa/setup');

        $subscriber = new AccountStatusSubscriber($security, $urlGenerator, $this->createMandatePolicy());
        $event = $this->createEvent('/admin/dashboard');

        $subscriber->onKernelController($event);

        $response = ($event->getController())();
        $this->assertSame('/profile/2fa/setup', $response->headers->get('Location'));
    }

    public function testDormantSuperAdminWithout2faIsAlsoForcedIntoSetup(): void
    {
        // getPrimaryRole() (droit acquis brut) classe ce compte Super Admin
        // même dormant (jamais élevé) — TwoFactorMandatePolicy s'appuie sur
        // cette classification, pas sur l'élévation PAM courante.
        $security = $this->createSecurity($this->createUser(true, true, ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']), editorArea: true);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())->method('generate')->with('profile_two_factor_setup')->willReturn('/profile/2fa/setup');

        $subscriber = new AccountStatusSubscriber($security, $urlGenerator, $this->createMandatePolicy());
        $event = $this->createEvent('/admin/dashboard');

        $subscriber->onKernelController($event);

        $response = ($event->getController())();
        $this->assertSame('/profile/2fa/setup', $response->headers->get('Location'));
    }

    public function testAdminWith2faIsLeftAlone(): void
    {
        $subscriber = $this->subscriber($this->createSecurity($this->createUser(true, true, ['ROLE_ADMIN'], totpEnabled: true), editorArea: true));
        $event = $this->createEvent('/admin/dashboard');
        $originalController = $event->getController();

        $subscriber->onKernelController($event);

        $this->assertSame($originalController, $event->getController());
    }
}
