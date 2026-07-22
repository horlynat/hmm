<?php

namespace App\Service;

use App\Message\SendEmail;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;

/**
 * Service central unifié pour la gestion des emails.
 *
 * - sendAsync() → file Messenger (worker) — emails non-critiques
 * - sendNow()   → envoi direct synchrone — emails critiques (token JWT, etc.)
 */
class EmailManager
{
    public function __construct(
        private MessageBusInterface $bus,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire(param: 'app.default_sender')] private string $defaultSender
    ) {}

    /**
     * Dispatch dans la file Messenger pour traitement en arrière-plan.
     * À utiliser pour tous les emails non-urgents (bienvenue, alertes, notifications).
     *
     * @param array<string, mixed> $context
     */
    public function sendAsync(
        string $to,
        string $subject,
        string $template,
        array $context = []
    ): void {
        $this->bus->dispatch(new SendEmail($to, $subject, $template, $context));
    }

    /**
     * Envoi immédiat et synchrone — bloque jusqu'à la réponse du serveur SMTP.
     * À utiliser uniquement quand le délai async est inacceptable :
     *   - Token JWT avec expiration courte
     *   - Lien de réinitialisation de mot de passe
     *   - Confirmation critique en temps réel
     *
     * @param array<string, mixed> $context
     */
    public function sendNow(
        string $to,
        string $subject,
        string $template,
        array $context = []
    ): bool {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->defaultSender, 'Mon Portfolio'))
                ->to($to)
                ->subject($subject)
                ->htmlTemplate("emails/{$template}.html.twig")
                ->context($context);

            $this->mailer->send($email);

            $this->logger->info('Email synchrone envoyé', [
                'to'      => $to,
                'subject' => $subject,
            ]);

            return true;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Échec envoi email synchrone : ' . $e->getMessage(), [
                'to'      => $to,
                'subject' => $subject,
            ]);

            return false;
        }
    }
}