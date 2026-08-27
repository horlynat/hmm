<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Comble un angle mort découvert en testant le canal fail2ban fichier
 * (cf. monolog.yaml, handler security_errors_file) : un échec de connexion
 * via le formulaire web (firewall `main`) ne remonte JAMAIS comme exception
 * au niveau kernel — AuthenticatorManager le capture en interne et journalise
 * "Authenticator failed." sur le canal Monolog natif `security`, à INFO, sans
 * IP, avec un texte qui ne matche aucun des deux motifs du filtre fail2ban
 * (`Authentication (failure|failed) for .* from <HOST>` /
 * `Invalid credentials.*"ip":"<HOST>"`). Résultat constaté en prod : même
 * après avoir posé un fichier réel pour `security_errors`, une vraie
 * tentative de connexion échouée n'y laissait toujours aucune trace —
 * ExceptionSubscriber (qui alimente ce canal) n'est jamais sollicité pour ce
 * cas précis, seulement pour les échecs qui remontent bruts jusqu'au kernel
 * (JWT sur l'API, AccessDenied d'un Voter).
 *
 * LoginFailureEvent, lui, est dispatché par AuthenticatorManager pour TOUT
 * échec sur N'IMPORTE QUEL firewall/authenticator (formulaire web ET JSON
 * login de l'API) — point d'accroche unique, indépendant du canal `security`
 * natif dont on ne maîtrise ni le niveau ni le format du message.
 */
final class LoginFailureLoggerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.security_errors')] private readonly LoggerInterface $securityErrorsLogger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [LoginFailureEvent::class => 'onLoginFailure'];
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        // Message fixe (pas celui, variable, de l'exception) : garantit le
        // "Invalid credentials." attendu par le filtre fail2ban quel que
        // soit le type précis d'échec (mauvais mot de passe, compte
        // inconnu, désactivé...) — le détail réel part dans le contexte,
        // pour le débogage, pas pour la détection.
        $this->securityErrorsLogger->warning('Invalid credentials.', [
            'ip' => $event->getRequest()->getClientIp(),
            'firewall' => $event->getFirewallName(),
            'reason' => $event->getException()->getMessage(),
        ]);
    }
}
