<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\HomeContent;

/**
 * Ressource publique en lecture seule : la table n'a qu'une ligne (gérée via
 * le back-office), exposée en collection pour que le frontend n'ait pas à
 * connaître un id — il prend simplement le premier (et unique) élément.
 */
#[ApiResource(
    stateOptions: new Options(entityClass: HomeContent::class),
    shortName: 'HomeContent',
    description: "Contenu narratif de la page d'accueil (hero, teaser à propos, pitch freelance, appel à l'action).",
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne le contenu de la page d'accueil (élément unique)."
        ),
    ],
    normalizationContext: ['groups' => ['api_public']],
)]
class HomeContentApiResource extends HomeContent
{
}
