<?php

namespace App\Controller\Api;

use App\Entity\SupportTicket;
use App\Entity\SupportTicketMessage;
use App\Enum\NotificationPriorityEnum;
use App\Exception\TooManyRequestsException;
use App\Repository\SupportTicketRepository;
use App\Service\AdminAlertNotifier;
use App\Service\PublicSubmissionThrottler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Accès invité (sans compte) à un ticket support par jeton opaque — mirroir
 * de App\Controller\Api\PasswordResetController : un contrôleur "plain" hors
 * API Platform, puisqu'il s'agit d'une recherche par clé secondaire secrète
 * (le jeton) plutôt que par id + expression de sécurité classique.
 *
 * Le jeton n'est jamais renvoyé (ni dans ces réponses, ni ailleurs) : le seul
 * canal de transmission au visiteur est l'email de confirmation/réponse.
 */
#[Route('/api/support_tickets', name: 'api_support_ticket_')]
final class SupportTicketPublicController extends AbstractController
{
    #[Route('/{token}', name: 'guest_view', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET'])]
    public function guestView(
        string $token,
        SupportTicketRepository $supportTicketRepository,
        PublicSubmissionThrottler $throttler,
    ): JsonResponse {
        try {
            $throttler->assertSupportTicketGuestAccessAllowed();
        } catch (TooManyRequestsException $e) {
            return $this->json(['detail' => $e->getMessage()], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $ticket = $supportTicketRepository->findOneByAccessToken($token);
        if (!$ticket instanceof SupportTicket) {
            return $this->json(['detail' => 'Ticket introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'subject' => $ticket->getSubject(),
            'status' => $ticket->getStatus()->value,
            'statusLabel' => $ticket->getStatus()->getLabel(),
            'messages' => array_map(
                static fn (SupportTicketMessage $message) => [
                    'body' => $message->getBody(),
                    'fromAdmin' => $message->isFromAdmin(),
                    'createdAt' => $message->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ],
                $ticket->getMessages()->toArray(),
            ),
        ]);
    }

    #[Route('/{token}/reply', name: 'guest_reply', requirements: ['token' => '[a-f0-9]{64}'], methods: ['POST'])]
    public function guestReply(
        string $token,
        Request $request,
        SupportTicketRepository $supportTicketRepository,
        PublicSubmissionThrottler $throttler,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
        AdminAlertNotifier $adminAlertNotifier,
    ): JsonResponse {
        try {
            $throttler->assertSupportTicketGuestAccessAllowed();
        } catch (TooManyRequestsException $e) {
            return $this->json(['detail' => $e->getMessage()], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $ticket = $supportTicketRepository->findOneByAccessToken($token);
        if (!$ticket instanceof SupportTicket) {
            return $this->json(['detail' => 'Ticket introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        $body = \is_array($payload) ? trim((string) ($payload['message'] ?? '')) : '';

        $violations = $validator->validate($body, [
            new Assert\NotBlank(message: 'Le message est obligatoire.'),
            new Assert\Length(min: 10, max: 5000, minMessage: 'Votre message doit contenir au moins {{ limit }} caractères.'),
        ]);
        if (\count($violations) > 0) {
            return $this->json(['detail' => $violations[0]->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $message = new SupportTicketMessage();
        $message->setBody($body);
        $message->setFromAdmin(false);
        $ticket->addMessage($message);
        $ticket->reopenIfResolved();
        $entityManager->flush();

        $adminAlertNotifier->alert(
            NotificationPriorityEnum::MEDIUM,
            'Nouvelle réponse client sur un ticket',
            \sprintf('%s (%s) — %s', $ticket->getName(), $ticket->getEmail(), $ticket->getSubject()),
        );

        return $this->json(['detail' => 'Votre réponse a été envoyée.']);
    }
}
