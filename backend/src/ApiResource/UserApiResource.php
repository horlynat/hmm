<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Entity\User;

#[ApiResource(
    operations: [
        // 📌 Liste des utilisateurs (admin uniquement)
        new GetCollection(
            normalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')"
        ),

        // 📌 Lire un utilisateur (public : profil simplifié)
        new Get(
            normalizationContext: ['groups' => ['api_user']]
        ),

        // 📌 Créer un utilisateur (admin)
        new Post(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')"
        ),

        // 📌 Mettre à jour un utilisateur (admin)
        new Put(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')"
        ),

        // 📌 Supprimer un utilisateur (admin)
        new Delete(
            security: "is_granted('ROLE_ADMIN')"
        ),
    ],
    normalizationContext: ['groups' => ['api_user']],
    denormalizationContext: ['groups' => ['api_admin']]
)]
class UserApiResource extends User
{
}
