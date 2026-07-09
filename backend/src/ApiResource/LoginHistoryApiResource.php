<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Delete;
use App\Entity\LoginHistory;

#[ApiResource(
    description: "Ressource API pour gérer l’historique des connexions des utilisateurs.",
    operations: [
        // 📌 Liste des connexions (admin uniquement)
        new GetCollection(
            normalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Retourne la liste des connexions des utilisateurs (admin uniquement)."
        ),

        // 📌 Lire une connexion (admin uniquement)
        new Get(
            normalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Retourne les détails d’une connexion spécifique (admin uniquement)."
        ),

        // 📌 Supprimer une entrée de connexion (admin uniquement)
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            description: "Supprime une entrée de l’historique des connexions (admin uniquement)."
        ),
    ],
    normalizationContext: ['groups' => ['api_admin']],
    denormalizationContext: ['groups' => ['api_admin']]
)]
class LoginHistoryApiResource extends LoginHistory
{
}
