<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Revérifie, à chaque requête sur une zone authentifiée, qu'un utilisateur
 * déjà connecté n'a pas été désactivé ou dévérifié entre-temps — et impose
 * la double authentification (TOTP) sur l'espace membre (clients et
 * collaborateurs, tout compte sous ROLE_USER sans ROLE_EDITOR).
 *
 * SecurityAuthenticator ne vérifie isActive()/isVerified() qu'au moment du
 * login (dans le UserBadge de authenticate()) : ContextListener recharge
 * bien le User depuis la base à chaque requête, mais rien ne relit ces deux
 * champs sur l'objet rechargé — un compte désactivé en cours de session
 * gardait donc l'accès jusqu'à sa prochaine reconnexion. C'est ce que ce
 * subscriber comble.
 *
 * La 2FA obligatoire suit le même principe plutôt qu'une étape ajoutée au
 * formulaire d'inscription (géré côté frontend Next.js, hors de ce dépôt) :
 * dès la première page de son espace, tout compte client/collaborateur non
 * encore équipé est redirigé vers l'activation (TwoFactorController::setup,
 * toujours en libre-service) et ne peut rien faire d'autre tant que ce n'est
 * pas fait — ce qui en fait de facto un passage obligé de l'inscription,
 * sans dépendre du frontend pour l'appliquer. Ne concerne pas les comptes
 * ROLE_EDITOR et plus (back-office), hors périmètre de cette demande.
 *
 * Écoute kernel.controller (pas kernel.request) : à ce stade, le routage et
 * l'AccessListener d'access_control (security.yaml) ont déjà tranché, donc
 * aucun raisonnement sur l'ordre de priorité des listeners de sécurité n'est
 * nécessaire ici.
 */
final class AccountStatusSubscriber implements EventSubscriberInterface
{
    /**
     * Zones authentifiées concernées par la revérification. Une page publique
     * reste accessible même à un compte bloqué avec un cookie de session
     * résiduel.
     */
    private const IN_SCOPE_PATTERN = '#^/(admin|profile|projects|mes-devis)(/|$)#';

    /**
     * Chemins toujours exemptés, même sous une zone ci-dessus : les deux
     * pages de blocage elles-mêmes (anti-boucle), l'authentification, le 2FA
     * (challenge de connexion ET écran de configuration en libre-service —
     * sans "profile/2fa" ici, un compte redirigé vers l'activation obligatoire
     * ne pourrait jamais atteindre l'écran qui la lui fait justement activer)
     * et le parcours de vérification d'email (qui doit rester atteignable par
     * un compte justement non vérifié).
     */
    private const SKIP_PATTERN = '#^/(admin/compte-bloque|profile/compte-bloque|profile/2fa|login|logout|2fa|verif/|renvoiverif)#';

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (1 !== preg_match(self::IN_SCOPE_PATTERN, $path) || 1 === preg_match(self::SKIP_PATTERN, $path)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // isGranted() applique la hiérarchie de rôles (role_hierarchy, security.yaml) ;
        // un ROLE_ADMIN sans ROLE_EDITOR explicitement listé sur son token doit tout de
        // même atterrir sur la page de blocage admin, pas profil (getRoleNames() brut
        // ne fait pas cette expansion).
        $isAdminArea = $this->security->isGranted('ROLE_EDITOR');

        if (!$user->isActive()) {
            $event->setController(fn () => new RedirectResponse($this->urlGenerator->generate(
                $isAdminArea ? 'admin_account_blocked' : 'profile_account_blocked',
                ['reason' => 'disabled'],
            )));

            return;
        }

        if (!$user->isVerified()) {
            $event->setController(fn () => new RedirectResponse($this->urlGenerator->generate(
                $isAdminArea ? 'admin_account_blocked' : 'profile_account_blocked',
                ['reason' => 'unverified'],
            )));

            return;
        }

        if ($user->isLocked()) {
            $event->setController(fn () => new RedirectResponse($this->urlGenerator->generate(
                $isAdminArea ? 'admin_account_blocked' : 'profile_account_blocked',
                ['reason' => 'locked'],
            )));

            return;
        }

        if ($user->isExpired()) {
            $event->setController(fn () => new RedirectResponse($this->urlGenerator->generate(
                $isAdminArea ? 'admin_account_blocked' : 'profile_account_blocked',
                ['reason' => 'expired'],
            )));

            return;
        }

        // 2FA obligatoire sur l'espace membre (client/collaborateur) uniquement —
        // le back-office (ROLE_EDITOR+) a sa propre gestion, hors périmètre ici.
        if (!$isAdminArea && !$user->isTotpAuthenticationEnabled()) {
            $event->setController(fn () => new RedirectResponse($this->urlGenerator->generate('profile_two_factor_setup')));
        }
    }
}
