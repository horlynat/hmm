<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Entity\Experience;

#[ApiResource(
    stateOptions: new Options(entityClass: Experience::class),
    shortName: 'Experience',
    description: "Ressource API pour gérer les expériences professionnelles.
    Permet de lister, consulter, créer, mettre à jour et supprimer des expériences.",
    operations: [
        // 📌 Liste des expériences (public)
        new GetCollection(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne la liste des expériences disponibles (accessible publiquement)."
        ),

        // 📌 Lire une expérience (public)
        new Get(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne les détails d’une expérience spécifique (accessible publiquement)."
        ),

        // 📌 Créer une expérience (admin / JWT requis)
        new Post(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Crée une nouvelle expérience. Accessible uniquement aux administrateurs authentifiés."
        ),

        // 📌 Mettre à jour une expérience (admin / JWT requis)
        new Put(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Met à jour une expérience existante. Accessible uniquement aux administrateurs authentifiés."
        ),

        // 📌 Supprimer une expérience (admin / JWT requis)
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            description: "Supprime une expérience existante. Accessible uniquement aux administrateurs authentifiés."
        ),
    ],
    normalizationContext: ['groups' => ['api_public']],
    denormalizationContext: ['groups' => ['api_admin']]
)]
class ExperienceApiResource extends Experience
{
}
