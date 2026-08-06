<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\ContactMessageApiResource;
use App\Entity\ContactMessage;
use App\Service\PublicSubmissionThrottler;
use Doctrine\ORM\EntityManagerInterface;

/**
 * ContactMessageApiResource n'étant pas lui-même mappé Doctrine (seul son
 * parent ContactMessage l'est), le PersistProcessor générique d'API Platform
 * ne le reconnaît pas comme une entité gérable et ne persiste ni ne flush
 * rien (cf. ManagerRegistry::getManagerForClass -> isTransient). On construit
 * donc explicitement une véritable ContactMessage à partir des champs publics
 * reçus, pour que la création soit effectivement enregistrée en base.
 */
final class ContactMessageCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PublicSubmissionThrottler $throttler,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ContactMessage
    {
        \assert($data instanceof ContactMessageApiResource);

        $this->throttler->assertFormSubmissionAllowed();

        $entity = new ContactMessage();
        $entity->setSource($data->getSource());
        $entity->setName($data->getName());
        $entity->setCompany($data->getCompany());
        $entity->setEmail($data->getEmail());
        $entity->setPhone($data->getPhone());
        $entity->setChannel($data->getChannel());
        $entity->setSlot($data->getSlot());
        $entity->setSubject($data->getSubject());
        $entity->setMessage($data->getMessage());

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }
}
