<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\QuoteRequestApiResource;
use App\Entity\QuoteRequest;
use App\Service\PublicSubmissionThrottler;
use Doctrine\ORM\EntityManagerInterface;

/**
 * QuoteRequestApiResource n'étant pas lui-même mappé Doctrine (seul son
 * parent QuoteRequest l'est), le PersistProcessor générique d'API Platform
 * ne le reconnaît pas comme une entité gérable et ne persiste ni ne flush
 * rien (même défaut que ContactMessageCreateProcessor). On construit donc
 * explicitement une véritable QuoteRequest à partir des champs publics
 * reçus. Le champ `user` reste volontairement null : les demandes de devis
 * publiques sont anonymes, aucun compte n'est requis.
 */
final class QuoteRequestCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PublicSubmissionThrottler $throttler,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): QuoteRequest
    {
        \assert($data instanceof QuoteRequestApiResource);

        $this->throttler->assertFormSubmissionAllowed();

        $entity = new QuoteRequest();
        $entity->setName($data->getName());
        $entity->setEmail($data->getEmail());
        $entity->setPhone($data->getPhone());
        $entity->setCategory($data->getCategory());
        $entity->setCategoryDetail($data->getCategoryDetail());
        $entity->setSource($data->getSource());
        $entity->setBudget($data->getBudget());
        $entity->setCurrency($data->getCurrency());
        $entity->setTimeline($data->getTimeline());
        $entity->setChannel($data->getChannel());
        $entity->setAttachmentName($data->getAttachmentName());
        $entity->setClarifications($data->getClarifications());
        $entity->setMessage($data->getMessage());

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }
}
