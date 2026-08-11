<?php

namespace App\Repository;

use App\Entity\AiAssistantDocumentChunk;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiAssistantDocumentChunk>
 */
class AiAssistantDocumentChunkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiAssistantDocumentChunk::class);
    }

    /**
     * Tous les chunks marqués publics — le volume de contenu d'un portfolio
     * (quelques dizaines de lignes) rend un scan brute-force largement
     * suffisant, pas besoin d'un filtre JSON côté SQL. Le flag `is_public`
     * est revérifié en PHP (isPublic()) même si en théorie seul du contenu
     * déjà public est ingéré — défense en profondeur (cf. AiAssistantDocumentChunk).
     *
     * @return AiAssistantDocumentChunk[]
     */
    public function findAllPublic(): array
    {
        return array_values(array_filter(
            $this->findAll(),
            static fn (AiAssistantDocumentChunk $chunk): bool => $chunk->isPublic(),
        ));
    }

    /**
     * @return AiAssistantDocumentChunk[]
     */
    public function findByEntity(string $entityType, int $entityId): array
    {
        return $this->findBy(['entityType' => $entityType, 'entityId' => $entityId]);
    }

    public function deleteByEntity(string $entityType, int $entityId): void
    {
        $em = $this->getEntityManager();
        foreach ($this->findByEntity($entityType, $entityId) as $chunk) {
            $em->remove($chunk);
        }
    }
}
