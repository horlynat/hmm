<?php

namespace App\ApiResource;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\SkillDomain;

/**
 * Domaines de compétence de la page À propos — lecture publique seule.
 * Aucune écriture par l'API : l'édition passe par le back-office (Twig,
 * CSRF, ROLE_ADMIN), ce qui ne laisse aucune surface d'écriture publique.
 */
#[ApiResource(
    stateOptions: new Options(entityClass: SkillDomain::class),
    shortName: 'SkillDomain',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['api_public']],
            order: ['position' => 'ASC', 'id' => 'ASC'],
            paginationEnabled: false,
        ),
    ],
)]
class SkillDomainApiResource extends SkillDomain
{
}
