<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Entity\Article;

#[ApiResource(
    description: "Ressource API pour gérer les articles du site. 
    Permet de lister, consulter, créer, mettre à jour et supprimer des articles.",
    operations: [
        // 📌 Liste des articles (public)
        new GetCollection(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne la liste des articles disponibles (accessible publiquement)."
        ),

        // 📌 Lire un article (public)
        new Get(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne les détails d’un article spécifique (accessible publiquement)."
        ),

        // 📌 Créer un article (admin / JWT requis)
        new Post(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Crée un nouvel article. Accessible uniquement aux administrateurs authentifiés."
        ),

        // 📌 Mettre à jour un article (admin / JWT requis)
        new Put(
            denormalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Met à jour un article existant. Accessible uniquement aux administrateurs authentifiés."
        ),

        // 📌 Supprimer un article (admin / JWT requis)
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            description: "Supprime un article existant. Accessible uniquement aux administrateurs authentifiés."
        ),
    ],
    normalizationContext: ['groups' => ['api_public']],
    denormalizationContext: ['groups' => ['api_admin']]
)]
class ArticleApiResource extends Article
{
}
