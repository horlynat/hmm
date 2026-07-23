<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Entity\SkillCategory;

#[ApiResource(
    stateOptions: new Options(entityClass: SkillCategory::class),
    shortName: 'SkillCategory',
    description: "Ressource API pour gérer les catégories de compétences.",
    operations: [
        // 📌 Liste des catégories (public)
        new GetCollection(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne la liste des catégories de compétences."
        ),

        // 📌 Lire une catégorie (public)
        new Get(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne les détails d’une catégorie spécifique."
        ),

        // 📌 Créer une catégorie (admin)
        new Post(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Crée une nouvelle catégorie de compétences (admin uniquement)."
        ),

        // 📌 Mettre à jour une catégorie (admin)
        new Put(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Met à jour une catégorie existante (admin uniquement)."
        ),

        // 📌 Supprimer une catégorie (admin)
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            description: "Supprime une catégorie existante (admin uniquement)."
        ),
    ],
    normalizationContext: ['groups' => ['api_public']],
    denormalizationContext: ['groups' => ['api_admin']]
)]
class SkillCategoryApiResource extends SkillCategory
{
}
