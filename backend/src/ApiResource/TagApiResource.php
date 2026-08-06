<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Entity\Tag;

#[ApiResource(
    description: "Ressource API pour gérer les tags associés aux articles.",
    operations: [
        // 📌 Liste des tags (public)
        new GetCollection(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne la liste des tags disponibles."
        ),

        // 📌 Lire un tag (public)
        new Get(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne les détails d’un tag spécifique."
        ),

        // 📌 Créer un tag (admin)
        new Post(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Crée un nouveau tag (admin uniquement)."
        ),

        // 📌 Mettre à jour un tag (admin)
        new Put(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Met à jour un tag existant (admin uniquement)."
        ),

        // 📌 Supprimer un tag (admin)
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            description: "Supprime un tag existant (admin uniquement)."
        ),
    ],
    normalizationContext: ['groups' => ['api_public']],
    denormalizationContext: ['groups' => ['api_admin']]
)]
class TagApiResource extends Tag
{
}
