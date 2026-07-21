<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Entity\Project;

#[ApiResource(
    description: "Ressource API pour gérer les projets du portfolio.
    Permet de lister, consulter, créer, mettre à jour et supprimer des projets.",
    operations: [
        // 📌 Liste des projets (public)
        new GetCollection(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne la liste des projets disponibles."
        ),

        // 📌 Lire un projet (public)
        new Get(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne les détails d’un projet spécifique."
        ),

        // 📌 Créer un projet (admin)
        new Post(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Crée un nouveau projet. Accessible uniquement aux administrateurs."
        ),

        // 📌 Mettre à jour un projet (admin)
        new Put(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Met à jour un projet existant. Accessible uniquement aux administrateurs."
        ),

        // 📌 Supprimer un projet (admin)
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            description: "Supprime un projet existant. Accessible uniquement aux administrateurs."
        ),
    ],
    normalizationContext: ['groups' => ['api_public']],
    denormalizationContext: ['groups' => ['api_admin']]
)]
class ProjectApiResource extends Project
{
}
