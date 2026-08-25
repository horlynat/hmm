<?php

namespace App\ApiResource;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Entity\NewsletterSubscriber;
use App\State\NewsletterSubscriberCreateProcessor;

/**
 * Inscription à la newsletter du blog — cf. docblock de App\Entity\
 * NewsletterSubscriber. Mêmes conventions que ContactMessageApiResource
 * (create public via processor dédié, lecture/suppression réservées à
 * l'admin) : pas encore de page de back-office dédiée pour parcourir les
 * abonnés, mais l'API le permet déjà (GET direct ou future page admin).
 */
#[ApiResource(
    stateOptions: new Options(entityClass: NewsletterSubscriber::class),
    shortName: 'NewsletterSubscriber',
    description: 'Ressource API pour gérer les inscriptions à la newsletter du blog.',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: 'Retourne la liste des abonnés à la newsletter (admin uniquement).'
        ),
        new Get(
            normalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Retourne les détails d'un abonné (admin uniquement)."
        ),
        new Post(
            denormalizationContext: ['groups' => ['api_public']],
            processor: NewsletterSubscriberCreateProcessor::class,
            description: 'Permet à un visiteur de s\'inscrire à la newsletter.'
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            description: 'Supprime un abonné (admin uniquement).'
        ),
    ],
    normalizationContext: ['groups' => ['api_public']],
    denormalizationContext: ['groups' => ['api_public']]
)]
class NewsletterSubscriberApiResource extends NewsletterSubscriber
{
}
