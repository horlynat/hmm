<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Entity\QuoteRequest;

#[ApiResource(
    stateOptions: new Options(entityClass: QuoteRequest::class),
    shortName: 'QuoteRequest',
    description: "Ressource API pour gérer les demandes de devis des utilisateurs.",
    operations: [
        // 📌 Liste des demandes (admin uniquement)
        new GetCollection(
            normalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Retourne la liste des demandes de devis (admin uniquement)."
        ),

        // 📌 Lire une demande (admin uniquement)
        new Get(
            normalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Retourne les détails d’une demande de devis (admin uniquement)."
        ),

        // 📌 Créer une demande (public)
        new Post(
            denormalizationContext: ['groups' => ['api_public']],
            description: "Permet à un utilisateur de créer une demande de devis."
        ),

        // 📌 Mettre à jour une demande (admin)
        new Put(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Met à jour une demande de devis (admin uniquement)."
        ),

        // 📌 Supprimer une demande (admin)
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            description: "Supprime une demande de devis (admin uniquement)."
        ),
    ],
    normalizationContext: ['groups' => ['api_public']],
    denormalizationContext: ['groups' => ['api_public']]
)]
class QuoteRequestApiResource extends QuoteRequest
{
}
