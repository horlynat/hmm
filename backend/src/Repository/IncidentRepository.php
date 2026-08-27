<?php

namespace App\Repository;

use App\Entity\Incident;
use App\Enum\IncidentCategoryEnum;
use App\Enum\IncidentStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Incident>
 */
class IncidentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Incident::class);
    }

    public function countOpen(): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.status != :resolved')
            ->setParameter('resolved', IncidentStatusEnum::RESOLVED)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Répartition par catégorie, du plus fréquent au moins fréquent — c'est
     * la vue "récurrence" : une catégorie avec un compte élevé signale un
     * problème structurel, pas un incident isolé.
     *
     * @return array<int, array{category: string, count: int}>
     */
    public function countByCategory(): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('i.category AS category', 'COUNT(i.id) AS count')
            ->groupBy('i.category')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getArrayResult()
        ;

        // getArrayResult() hydrate une colonne enumType en instance d'enum,
        // pas en scalaire — constaté en pratique : le template plantait sur
        // `row.category|upper` (mb_strtoupper() attend une string).
        return array_map(
            static function (array $row): array {
                $category = $row['category'];

                return [
                    'category' => $category instanceof IncidentCategoryEnum ? $category->value : (string) $category,
                    'count' => (int) $row['count'],
                ];
            },
            $rows,
        );
    }

    /**
     * Catégories récurrentes uniquement (seuil configurable) — ce sont
     * celles qui méritent qu'on regarde la cause racine plutôt que de
     * corriger au cas par cas à chaque nouvelle occurrence.
     *
     * @return array<int, array{category: string, count: int}>
     */
    public function findRecurringCategories(int $minOccurrences = 2): array
    {
        return array_values(array_filter(
            $this->countByCategory(),
            static fn (array $row): bool => $row['count'] >= $minOccurrences,
        ));
    }
}
