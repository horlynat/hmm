<?php

namespace App\Service;

use App\Doctrine\ProjectVisibilityExtension;

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
 * Construit/rafraîchit les chunks de contexte (document_embedding) pour une
 * entité de contenu du portfolio. Project/Article/Experience/SkillCategory
 * alimentent le corpus statique envoyé à Claude (cf. docblock de
 * App\State\AiAssistantChatProcessor) — toutes déjà exposées sans filtre par
 * l'API publique (opérations GetCollection sans `security:`). Exception :
 * les projets, que l'API publique filtre par statut
 * (ProjectVisibilityExtension::PUBLIC_STATUSES). Un projet client en
 * attente (par exemple issu d'une demande de devis) n'est donc ni envoyé à
 * Gemini, ni exposé à l'assistant public ; ses chunks sont retirés dès qu'il
 * n'est plus visible.
 *
 * SkillCategory est ingérée au niveau de la catégorie (pas de chaque Skill
 * individuellement) : un Skill seul ("Symfony, niveau 9/10") est un chunk
 * trop pauvre en contexte — une catégorie complète ("Backend : Symfony, PHP,
 * API Platform...") forme un chunk dense et cohérent, dans le même esprit
 * qu'un Project ou une Experience.
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
        private readonly AiAssistantOutputSanitizer $outputSanitizer = new AiAssistantOutputSanitizer(),
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

        if ($entity instanceof Project && !\in_array($entity->getStatus()->value, ProjectVisibilityExtension::PUBLIC_STATUSES, true)) {
            $this->chunkRepository->deleteByEntity($entityType, $entityId);
            $this->entityManager->flush();

            return;
        }

        // Première barrière : la configuration de sécurité ne part jamais chez
        // Gemini (phrase par phrase, ligne par ligne) — ni dans le texte stocké.
        $sourceText = $this->outputSanitizer->withoutSecurityConfig($this->extractSourceText($entityType, $entity));
        if ('' === trim($sourceText)) {
            // Plus rien à dire : l'ancien chunk ne doit pas survivre au contenu vidé.
            $this->chunkRepository->deleteByEntity($entityType, $entityId);
            $this->entityManager->flush();

            return;
        }

        try {
            $summary = $this->geminiClient->summarize($sourceText);
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

        $cleanSummary = $this->outputSanitizer->withoutSecurityConfig($summary['summary']);
        if ('' === trim($cleanSummary)) {
            // Rien de publiable : aucun chunk plutôt qu'un chunk vide.
            return;
        }

        // Deuxième barrière (la consigne de Gemini est la première) : toute phrase
        // du résumé qui nomme la configuration de sécurité est retirée avant
        // stockage — c'est ce résumé, et lui seul, qui atteint le chat public.
        $chunk = (new AiAssistantDocumentChunk())
            ->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setChunkIndex(0)
            ->setChunkText($sourceText)
            ->setChunkSummary($cleanSummary)
            ->setMetadata(['is_public' => true, 'label' => $this->extractLabel($entityType, $entity)]);

        $this->entityManager->persist($chunk);
        $this->entityManager->flush();
    }

    private function extractSourceText(string $entityType, object $entity): string
    {
        return match ($entityType) {
            'Project' => $this->extractProjectText($entity),
            'Article' => $entity instanceof Article
                ? trim(sprintf("%s\n\n%s", $entity->getTitle(), self::plainText($entity->getContent())))
                : '',
            'Experience' => $entity instanceof Experience
                ? trim(sprintf(
                    "%s chez %s\n\n%s",
                    $entity->getRole(),
                    $entity->getCompany(),
                    self::plainText((string) $entity->getDescription()),
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

        // Texte brut : le HTML de l'éditeur riche n'a rien à faire dans un résumé.
        $parts = [$entity->getTitle(), self::plainText($entity->getDescription())];

        $info = $entity->getInfo();
        if (null !== $info) {
            if (null !== $info->getRole()) {
                $parts[] = 'Rôle : ' . $info->getRole();
            }
            if ([] !== $info->getObjectives()) {
                $parts[] = 'Objectifs : ' . implode(', ', $info->getObjectives());
            }
            // Pile technique jamais ingérée : elle décrit la configuration d'un
            // système déployé (zéro information de sécurité, 2026-11-26).
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

    /**
     * Texte brut d'un contenu HTML du back-office : les fins de bloc deviennent
     * des retours à la ligne (découpage phrase par phrase du filtre de sécurité),
     * les balises disparaissent, les entités sont décodées.
     */
    private static function plainText(string $html): string
    {
        $text = (string) preg_replace('/<\/(?:p|li|h[1-6]|div|tr|blockquote)>|<br\s*\/?>/i', "\n", $html);
        $text = html_entity_decode(strip_tags((string) preg_replace('/<[^>]*>/', '$0 ', $text)), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace(['/[ \t]+/u', '/ *\R+ */u'], [' ', "\n"], $text));
    }
}
