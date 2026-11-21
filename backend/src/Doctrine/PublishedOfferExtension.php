<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Offer;
use Doctrine\ORM\QueryBuilder;

/**
 * L'API des offres (OfferApiResource) est publique : sans ce filtre, une
 * offre en brouillon (prix, texte non relus) serait lisible avant sa
 * publication. Appliqué à toute opération de lecture sur Offer — collection
 * comme élément, si une opération Get était ajoutée un jour — sans exception
 * admin : l'admin consulte ses brouillons dans le back-office, pas par l'API.
 */
final class PublishedOfferExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->filter($queryBuilder, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->filter($queryBuilder, $resourceClass);
    }

    private function filter(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if (Offer::class !== $resourceClass && !is_subclass_of($resourceClass, Offer::class)) {
            return;
        }

        $queryBuilder->andWhere(sprintf('%s.published = true', $queryBuilder->getRootAliases()[0]));
    }
}
