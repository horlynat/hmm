<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Entity\Skill;

#[ApiResource(
    stateOptions: new Options(entityClass: Skill::class),
    shortName: 'Skill',
    description: "Ressource API pour gérer les compétences (skills).",
    operations: [
        // 📌 Liste des compétences (public)
        new GetCollection(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne la liste des compétences disponibles."
        ),

        // 📌 Lire une compétence (public)
        new Get(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne les détails d’une compétence spécifique."
        ),

        // 📌 Créer une compétence (admin)
        new Post(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Crée une nouvelle compétence (admin uniquement)."
        ),

        // 📌 Mettre à jour une compétence (admin)
        new Put(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Met à jour une compétence existante (admin uniquement)."
        ),

        // 📌 Supprimer une compétence (admin)
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            description: "Supprime une compétence existante (admin uniquement)."
        ),
    ],
    normalizationContext: ['groups' => ['api_public']],
    denormalizationContext: ['groups' => ['api_admin']]
)]
class SkillApiResource extends Skill
{
}
