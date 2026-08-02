<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\QuoteRequestApiResource;
use App\Entity\QuoteRequest;
use App\Entity\User;
use App\Service\PublicSubmissionThrottler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * QuoteRequestApiResource n'étant pas lui-même mappé Doctrine (seul son
 * parent QuoteRequest l'est), le PersistProcessor générique d'API Platform
 * ne le reconnaît pas comme une entité gérable et ne persiste ni ne flush
 * rien (même défaut que ContactMessageCreateProcessor). On construit donc
 * explicitement une véritable QuoteRequest à partir des champs publics
 * reçus. Aucun compte n'est requis pour soumettre un devis (formulaire
 * public) — mais si la requête porte un JWT valide (utilisateur déjà
 * connecté sur le frontend Next.js), on rattache la demande à son compte
 * pour qu'elle apparaisse dans « Mes devis » sans intervention manuelle d'un
 * admin.
 */
final class QuoteRequestCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PublicSubmissionThrottler $throttler,
        private readonly Security $security,
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

        $currentUser = $this->security->getUser();
        if ($currentUser instanceof User) {
            $entity->setUser($currentUser);
        }

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }
}
