<?php

namespace App\Service;

use App\Entity\AiAssistantDocumentChunk;
use App\Entity\Article;
use App\Entity\Experience;
use App\Entity\Project;
use App\Entity\Skill;
use App\Entity\SkillCategory;
use App\Repository\AiAssistantDocumentChunkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Construit/rafraîchit les chunks RAG (document_embedding) pour une entité de
 * contenu du portfolio. Project/Article/Experience/SkillCategory alimentent
 * le RAG — toutes déjà exposées sans filtre par l'API publique (opérations
 * GetCollection sans `security:`), donc déjà du contenu public à 100 % :
 * aucun filtre de statut supplémentaire n'est nécessaire ici.
 *
 * SkillCategory est ingérée au niveau de la catégorie (pas de chaque Skill
 * individuellement) : un Skill seul ("Symfony, niveau 9/10") est un chunk
 * trop pauvre en signal pour la similarité cosinus — une catégorie complète
 * ("Backend : Symfony, PHP, API Platform...") forme un chunk dense et
 * cohérent, dans le même esprit qu'un Project ou une Experience.
 */
final class AiAssistantIngestionService
{
    private const ENTITY_CLASSES = [
        'Project' => Project::class,
        'Article' => Article::class,
        'Experience' => Experience::class,
        'SkillCategory' => SkillCategory::class,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GeminiIngestionClient $geminiClient,
        private readonly AiAssistantDocumentChunkRepository $chunkRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function ingest(string $entityType, int $entityId): void
    {
        $class = self::ENTITY_CLASSES[$entityType] ?? null;
        if (null === $class) {
            $this->logger->warning('AiAssistantIngestionService : type d\'entité inconnu.', ['entityType' => $entityType]);

            return;
        }

        $entity = $this->entityManager->getRepository($class)->find($entityId);

        if (null === $entity) {
            // Entité supprimée entre l'émission du message et son traitement : on retire ses chunks et on s'arrête.
            $this->chunkRepository->deleteByEntity($entityType, $entityId);
            $this->entityManager->flush();

            return;
        }

        $sourceText = $this->extractSourceText($entityType, $entity);
        if ('' === trim($sourceText)) {
            return;
        }

        try {
            $summary = $this->geminiClient->summarize($sourceText);
            $embedding = $this->geminiClient->embed($summary['summary']);
        } catch (\Throwable $e) {
            $this->logger->error('AiAssistantIngestionService : ingestion échouée, chunk existant conservé.', [
                'entityType' => $entityType,
                'entityId' => $entityId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $this->chunkRepository->deleteByEntity($entityType, $entityId);
        $this->entityManager->flush();

        $chunk = (new AiAssistantDocumentChunk())
            ->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setChunkIndex(0)
            ->setChunkText($sourceText)
            ->setChunkSummary($summary['summary'])
            ->setEmbedding($embedding['vector'])
            ->setMetadata(['is_public' => true, 'label' => $this->extractLabel($entityType, $entity)]);

        $this->entityManager->persist($chunk);
        $this->entityManager->flush();
    }

    private function extractSourceText(string $entityType, object $entity): string
    {
        return match ($entityType) {
            'Project' => $this->extractProjectText($entity),
            'Article' => $entity instanceof Article
                ? trim(sprintf("%s\n\n%s", $entity->getTitle(), $entity->getContent()))
                : '',
            'Experience' => $entity instanceof Experience
                ? trim(sprintf(
                    "%s chez %s\n\n%s",
                    $entity->getRole(),
                    $entity->getCompany(),
                    $entity->getDescription(),
                ))
                : '',
            'SkillCategory' => $entity instanceof SkillCategory ? $this->extractSkillCategoryText($entity) : '',
            default => '',
        };
    }

    private function extractSkillCategoryText(SkillCategory $entity): string
    {
        $skills = array_map(
            static fn (Skill $skill): string => sprintf('%s (niveau %d/10)', $skill->getName(), $skill->getLevel()),
            $entity->getSkill()->toArray(),
        );

        if ([] === $skills) {
            return '';
        }

        return sprintf('Compétences — %s : %s.', $entity->getName(), implode(', ', $skills));
    }

    private function extractProjectText(object $entity): string
    {
        if (!$entity instanceof Project) {
            return '';
        }

        $parts = [$entity->getTitle(), $entity->getDescription()];

        $info = $entity->getInfo();
        if (null !== $info) {
            if (null !== $info->getRole()) {
                $parts[] = 'Rôle : ' . $info->getRole();
            }
            if ([] !== $info->getObjectives()) {
                $parts[] = 'Objectifs : ' . implode(', ', $info->getObjectives());
            }
            if ([] !== $info->getTechStack()) {
                $parts[] = 'Stack technique : ' . implode(', ', array_column($info->getTechStack(), 'name'));
            }
            foreach ($info->getChallenges() as $challenge) {
                $parts[] = sprintf('Défi : %s — Solution : %s', $challenge['problem'] ?? '', $challenge['solution'] ?? '');
            }
            foreach ($info->getResults() as $result) {
                $parts[] = sprintf('%s : %s', $result['label'] ?? '', $result['value'] ?? '');
            }
        }

        return trim(implode("\n\n", array_filter($parts)));
    }

    private function extractLabel(string $entityType, object $entity): string
    {
        return match (true) {
            $entity instanceof Project, $entity instanceof Article => $entity->getTitle(),
            $entity instanceof Experience => sprintf('%s — %s', $entity->getRole(), $entity->getCompany()),
            $entity instanceof SkillCategory => 'Compétences — ' . $entity->getName(),
            default => $entityType,
        };
    }
}
