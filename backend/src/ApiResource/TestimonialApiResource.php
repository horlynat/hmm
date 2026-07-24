<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Entity\Testimonial;
use App\State\TestimonialCreateProcessor;

#[ApiResource(
    stateOptions: new Options(entityClass: Testimonial::class),
    shortName: 'Testimonial',
    description: "Ressource API pour gérer les témoignages (avis clients).",
    operations: [
        // 📌 Liste des témoignages (public)
        new GetCollection(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne la liste des témoignages disponibles."
        ),

        // 📌 Lire un témoignage (public)
        new Get(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne les détails d’un témoignage spécifique."
        ),

        // 📌 Créer un témoignage (public)
        new Post(
            denormalizationContext: ['groups' => ['api_public']],
            processor: TestimonialCreateProcessor::class,
            description: "Permet à un client de créer un témoignage."
        ),

        // 📌 Mettre à jour un témoignage (admin)
        new Put(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Met à jour un témoignage existant (admin uniquement)."
        ),

        // 📌 Supprimer un témoignage (admin)
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            description: "Supprime un témoignage existant (admin uniquement)."
        ),
    ],
    normalizationContext: ['groups' => ['api_public']],
    denormalizationContext: ['groups' => ['api_public']]
)]
class TestimonialApiResource extends Testimonial
{
}
