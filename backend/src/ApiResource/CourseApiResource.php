<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Entity\Course;

#[ApiResource(
    stateOptions: new Options(entityClass: Course::class),
    shortName: 'Course',
    description: "Ressource API pour gérer les cours.
    Permet de lister, consulter, créer, mettre à jour et supprimer des cours.",
    operations: [
        // 📌 Liste des cours (public)
        new GetCollection(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne la liste des cours disponibles (accessible publiquement)."
        ),

        // 📌 Lire un cours (public)
        new Get(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne les détails d’un cours spécifique (accessible publiquement)."
        ),

        // 📌 Créer un cours (admin / JWT requis)
        new Post(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Crée un nouveau cours. Accessible uniquement aux administrateurs authentifiés."
        ),

        // 📌 Mettre à jour un cours (admin / JWT requis)
        new Put(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Met à jour un cours existant. Accessible uniquement aux administrateurs authentifiés."
        ),

        // 📌 Supprimer un cours (admin / JWT requis)
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            description: "Supprime un cours existant. Accessible uniquement aux administrateurs authentifiés."
        ),
    ],
    normalizationContext: ['groups' => ['api_public']],
    denormalizationContext: ['groups' => ['api_admin']]
)]
class CourseApiResource extends Course
{
}
