<?php

// ✅ Fichier : src/MessageHandler/SendEmailHandler.php
namespace App\MessageHandler;

use App\Message\SendEmail;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\ExceptionInterface as MimeException;

#[AsMessageHandler]
class SendEmailHandler
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire(param: 'app.default_sender')] private string $defaultSender,
    ) {}

    public function __invoke(SendEmail $message): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->defaultSender, 'Horlynat | Portail Digital'))
                ->to($message->to)
                ->subject($message->subject)
                ->htmlTemplate("emails/{$message->template}.html.twig")
                ->context($message->context);

            $this->mailer->send($email);

            $this->logger->info('Email envoyé', ['to' => $message->to, 'subject' => $message->subject]);

        } catch (MimeException $e) { // RfcCompliance + InvalidArgument (caractère de contrôle)
            // Contre-audit B3 : adresse que le mailer ne pourra jamais accepter
            // — inutile de retenter : pas de retries, le message part directement
            // dans la file `failed` (traçable), au lieu de 3 tentatives vaines.
            $this->logger->warning('Adresse e-mail non conforme, envoi abandonné : ' . $e->getMessage(), ['subject' => $message->subject]);
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi email : ' . $e->getMessage(), ['to' => $message->to]);
            throw $e;
        }
    }
}