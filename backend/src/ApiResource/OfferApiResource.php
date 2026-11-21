<?php

namespace App\ApiResource;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Offer;

/**
 * Offres du site public — LECTURE SEULE, offres publiées uniquement
 * (App\Doctrine\PublishedOfferExtension).
 *
 * Une seule opération, volontairement : la liste complète (quelques offres,
 * pagination inutile) que le frontend filtre par slug pour la page de
 * détail. Aucune écriture par l'API : l'édition passe exclusivement par le
 * back-office Twig (App\Controller\Admin\AdminOfferController, ROLE_ADMIN,
 * CSRF, journal d'audit) — moins de surface qu'un Post/Put/Delete JWT.
 */
#[ApiResource(
    stateOptions: new Options(entityClass: Offer::class),
    shortName: 'Offer',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['offer_public']],
            order: ['position' => 'ASC', 'id' => 'ASC'],
            paginationEnabled: false,
            description: 'Liste des offres publiées, dans leur ordre d\'affichage.',
        ),
    ],
)]
class OfferApiResource extends Offer
{
}
