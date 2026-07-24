<?php

namespace App\Service;

use App\Exception\TooManyRequestsException;
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

    private function assert(RateLimiterFactory $factory): void
    {
        $ip = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';

        if (!$factory->create($ip)->consume(1)->isAccepted()) {
            throw new TooManyRequestsException('Trop de tentatives. Merci de réessayer plus tard.');
        }
    }
}
