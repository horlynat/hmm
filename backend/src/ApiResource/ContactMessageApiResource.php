<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use App\Entity\ContactMessage;

#[ApiResource(
    description: "Ressource API pour gérer les messages de contact envoyés via le site.",
    operations: [
        // 📌 Liste des messages (admin uniquement)
        new GetCollection(
            normalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Retourne la liste des messages de contact (admin uniquement)."
        ),

        // 📌 Lire un message (admin uniquement)
        new Get(
            normalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Retourne les détails d’un message de contact (admin uniquement)."
        ),

        // 📌 Créer un message (public)
        new Post(
            denormalizationContext: ['groups' => ['api_public']],
            description: "Permet à un utilisateur de créer un message de contact."
        ),

        // 📌 Supprimer un message (admin uniquement)
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            description: "Supprime un message de contact (admin uniquement)."
        ),
    ],
    normalizationContext: ['groups' => ['api_public']],
    denormalizationContext: ['groups' => ['api_public']]
)]
class ContactMessageApiResource extends ContactMessage
{
}
