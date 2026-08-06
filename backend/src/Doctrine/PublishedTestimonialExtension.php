<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use App\Entity\Testimonial;
use Doctrine\ORM\QueryBuilder;

/**
 * Les opérations Get/GetCollection de TestimonialApiResource sont publiques
 * (voir TestimonialApiResource) et, sans ce filtre, renvoyaient aussi les
 * témoignages non modérés (publishedAt = null) — contournant le workflow de
 * validation admin (AdminTestimonialController::publish, TESTIMONIAL_APPROVE).
 * Put/Delete restent volontairement non filtrées : réservées à ROLE_ADMIN,
 * qui doit pouvoir gérer un témoignage même non publié.
 */
final class PublishedTestimonialExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->filterIfApplicable($queryBuilder, $resourceClass, $operation, GetCollection::class);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->filterIfApplicable($queryBuilder, $resourceClass, $operation, Get::class);
    }

    private function filterIfApplicable(QueryBuilder $queryBuilder, string $resourceClass, ?Operation $operation, string $expectedOperationClass): void
    {
        if (Testimonial::class !== $resourceClass || !$operation instanceof $expectedOperationClass) {
            return;
        }

        $queryBuilder->andWhere(sprintf('%s.publishedAt IS NOT NULL', $queryBuilder->getRootAliases()[0]));
    }
}
