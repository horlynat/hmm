<?php

// ✅ Fichier : src/MessageHandler/SendEmailHandler.php
namespace App\MessageHandler;

use App\Message\SendEmail;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

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
                ->from(new Address($this->defaultSender, 'Mon Portfolio'))
                ->to($message->to)
                ->subject($message->subject)
                ->htmlTemplate("emails/{$message->template}.html.twig")
                ->context($message->context);

            $this->mailer->send($email);

            $this->logger->info('Email envoyé', ['to' => $message->to, 'subject' => $message->subject]);

        } catch (TransportExceptionInterface|\Throwable $e) {
            $this->logger->error('Échec envoi email : ' . $e->getMessage(), ['to' => $message->to]);
            throw $e;
        }
    }
}