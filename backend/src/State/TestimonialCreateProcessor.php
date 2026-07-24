<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\TestimonialApiResource;
use App\Entity\Testimonial;
use App\Service\PublicSubmissionThrottler;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Même défaut que ContactMessageCreateProcessor : TestimonialApiResource
 * n'est pas mappé Doctrine, le PersistProcessor générique ne persiste donc
 * rien pour cette classe. On construit explicitement un vrai Testimonial.
 */
final class TestimonialCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PublicSubmissionThrottler $throttler,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Testimonial
    {
        \assert($data instanceof TestimonialApiResource);

        $this->throttler->assertFormSubmissionAllowed();

        $entity = new Testimonial();
        $entity->setAuthor($data->getAuthor());
        $entity->setContent($data->getContent());
        $entity->setRating($data->getRating());

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }
}
