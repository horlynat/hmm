<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Entity\AiAssistantEntry;

#[ApiResource(
    stateOptions: new Options(entityClass: AiAssistantEntry::class),
    shortName: 'AiAssistantEntry',
    description: "Ressource API pour gérer les entrées de FAQ de l'assistant IA (suggestion, mots-clés, réponse).",
    operations: [
        // 📌 Liste des entrées (public)
        new GetCollection(
            normalizationContext: ['groups' => ['api_public']],
            order: ['sortOrder' => 'ASC'],
            description: "Retourne la liste ordonnée des entrées de l'assistant IA."
        ),

        // 📌 Lire une entrée (public)
        new Get(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne les détails d’une entrée spécifique."
        ),

        // 📌 Créer une entrée (admin)
        new Post(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Crée une nouvelle entrée (admin uniquement)."
        ),

        // 📌 Mettre à jour une entrée (admin)
        new Put(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Met à jour une entrée existante (admin uniquement)."
        ),

        // 📌 Supprimer une entrée (admin)
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            description: "Supprime une entrée existante (admin uniquement)."
        ),
    ],
    normalizationContext: ['groups' => ['api_public']],
    denormalizationContext: ['groups' => ['api_admin']]
)]
class AiAssistantEntryApiResource extends AiAssistantEntry
{
}
