<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\AiAssistantSettings;

/**
 * Ressource publique en lecture seule : la table n'a qu'une ligne (gérée via
 * le back-office), exposée en collection pour que le frontend n'ait pas à
 * connaître un id — il prend simplement le premier (et unique) élément.
 */
#[ApiResource(
    stateOptions: new Options(entityClass: AiAssistantSettings::class),
    shortName: 'AiAssistantSettings',
    description: "Réglages bilingues du widget assistant IA (message d'accueil, réponse par défaut).",
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['api_public']],
            description: "Retourne les réglages de l'assistant IA (élément unique)."
        ),
    ],
    normalizationContext: ['groups' => ['api_public']],
)]
class AiAssistantSettingsApiResource extends AiAssistantSettings
{
}
