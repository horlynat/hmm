<?php

namespace App\Service;

use App\Exception\TooManyRequestsException;
use App\Security\ClientIpRateLimitKey;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Anti-spam par IP pour les formulaires publics non protégés jusqu'ici
 * (contact, devis, témoignage, inscription) — cf. audit de sécurité.
 */
final class PublicSubmissionThrottler
{
    public function __construct(
        #[Autowire(service: 'limiter.public_form_submission')] private readonly RateLimiterFactory $formLimiter,
        #[Autowire(service: 'limiter.registration_attempt')] private readonly RateLimiterFactory $registrationLimiter,
        #[Autowire(service: 'limiter.ai_assistant_chat')] private readonly RateLimiterFactory $aiAssistantLimiter,
        #[Autowire(service: 'limiter.support_ticket_guest_access')] private readonly RateLimiterFactory $supportTicketGuestLimiter,
        #[Autowire(service: 'limiter.quote_qualify')] private readonly RateLimiterFactory $quoteQualifyLimiter,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @throws TooManyRequestsException
     */
    public function assertFormSubmissionAllowed(): void
    {
        $this->assert($this->formLimiter);
    }

    /**
     * @throws TooManyRequestsException
     */
    public function assertRegistrationAllowed(): void
    {
        $this->assert($this->registrationLimiter);
    }

    /**
     * @throws TooManyRequestsException
     */
    public function assertAiAssistantAllowed(): void
    {
        $this->assert($this->aiAssistantLimiter);
    }

    /**
     * @throws TooManyRequestsException
     */
    public function assertSupportTicketGuestAccessAllowed(): void
    {
        $this->assert($this->supportTicketGuestLimiter);
    }

    /**
     * @throws TooManyRequestsException
     */
    public function assertQuoteQualifyAllowed(): void
    {
        $this->assert($this->quoteQualifyLimiter);
    }

    private function assert(RateLimiterFactory $factory): void
    {
        // Clé par préfixe IPv6 /64 (IPv4 inchangée) : sinon un seul hôte IPv6
        // contourne le limiteur en changeant d'adresse à chaque requête.
        $ip = ClientIpRateLimitKey::fromIp($this->requestStack->getCurrentRequest()?->getClientIp());

        if (!$factory->create($ip)->consume(1)->isAccepted()) {
            throw new TooManyRequestsException('Trop de tentatives. Merci de réessayer plus tard.');
        }
    }
}
