<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\SupportTicketApiResource;
use App\Entity\SupportTicket;
use App\Entity\SupportTicketMessage;
use App\Entity\User;
use App\Enum\NotificationPriorityEnum;
use App\Service\AccountLinkResolver;
use App\Service\AdminAlertNotifier;
use App\Service\EmailManager;
use App\Service\PublicSubmissionThrottler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * SupportTicketApiResource n'étant pas lui-même mappé Doctrine (seul son
 * parent SupportTicket l'est), le PersistProcessor générique d'API Platform
 * ne le reconnaît pas comme une entité gérable — même défaut que
 * ContactMessageCreateProcessor/QuoteRequestCreateProcessor. On construit
 * donc explicitement un vrai SupportTicket + son premier SupportTicketMessage
 * (le message d'ouverture fait partie du fil comme n'importe quelle réponse
 * ultérieure, pas un champ séparé).
 *
 * @implements ProcessorInterface<SupportTicketApiResource, SupportTicket>
 */
final class SupportTicketCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PublicSubmissionThrottler $throttler,
        private readonly Security $security,
        private readonly AdminAlertNotifier $adminAlertNotifier,
        private readonly EmailManager $emailManager,
        private readonly AccountLinkResolver $accountLinkResolver,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SupportTicket
    {
        $this->throttler->assertFormSubmissionAllowed();

        $entity = new SupportTicket();
        $entity->setName($data->getName());
        $entity->setEmail($data->getEmail());
        $entity->setSubject($data->getSubject());
        $entity->setAccessToken(bin2hex(random_bytes(32)));

        $currentUser = $this->security->getUser();
        if ($currentUser instanceof User) {
            $entity->setUser($currentUser);
        }

        $firstMessage = new SupportTicketMessage();
        $firstMessage->setBody($data->getMessage());
        $firstMessage->setFromAdmin(false);
        $entity->addMessage($firstMessage);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->adminAlertNotifier->alert(
            NotificationPriorityEnum::MEDIUM,
            'Nouveau ticket support',
            \sprintf('%s (%s) — %s', $entity->getName(), $entity->getEmail(), $entity->getSubject()),
        );

        $threadUrl = $this->accountLinkResolver->resolveGuestLink('/support/'.$entity->getAccessToken());
        $this->emailManager->sendAsync(
            to: $entity->getEmail(),
            subject: 'Votre ticket a été créé',
            template: 'support_ticket_created',
            context: [
                'clientName' => $entity->getName(),
                'subject' => $entity->getSubject(),
                'threadUrl' => $threadUrl,
            ],
        );

        return $entity;
    }
}
