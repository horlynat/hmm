<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use App\Entity\Project;
use App\Enum\ProjectStatusEnum;
use Doctrine\ORM\QueryBuilder;

/**
 * Les opérations Get/GetCollection de ProjectApiResource sont publiques (voir
 * ProjectApiResource) et, sans ce filtre, exposaient aussi les projets à
 * venir, suspendus ou en collaboration — des statuts internes qui n'ont pas
 * vocation à apparaître sur le portfolio public. Seuls "en cours" et
 * "terminé" sont montrés au grand public. Put/Delete restent volontairement
 * non filtrées : réservées à ROLE_ADMIN, qui doit pouvoir gérer un projet
 * quel que soit son statut.
 */
final class ProjectVisibilityExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    private const PUBLIC_STATUSES = [
        ProjectStatusEnum::IN_PROGRESS->value,
        ProjectStatusEnum::COMPLETED->value,
    ];

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
        if (Project::class !== $resourceClass || !$operation instanceof $expectedOperationClass) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere(sprintf('%s.status IN (:visibleStatuses)', $alias))
            ->setParameter('visibleStatuses', self::PUBLIC_STATUSES);
    }
}
