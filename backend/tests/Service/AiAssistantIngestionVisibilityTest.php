<?php

namespace App\Tests\Service;

use App\Entity\AiAssistantDocumentChunk;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectStatusEnum;
use App\Repository\AiAssistantDocumentChunkRepository;
use App\Service\AiAssistantIngestionService;
use App\Service\GeminiIngestionClient;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Faille corrigée (contre-audit) : tout projet, même un projet client en
 * attente issu d'une demande de devis, était résumé par Gemini et injecté
 * dans le contexte de l'assistant PUBLIC (is_public = true), alors que l'API
 * publique ne montre que les statuts en cours / terminé. Désormais un projet
 * non visible n'est ni envoyé à Gemini, ni conservé dans le contexte.
 */
final class AiAssistantIngestionVisibilityTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private int $geminiCalls = 0;
    /** @var list<string> Corps des requêtes envoyées à Gemini. */
    private array $geminiBodies = [];
    private string $geminiSummary = 'Résumé de test.';
    /** @var list<object> */
    private array $created = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->created) as $entity) {
            if ($entity instanceof Project) {
                $this->chunks()->deleteByEntity('Project', (int) $entity->getId());
            }
            $managed = $this->em->find($entity::class, $entity->getId());
            if (null !== $managed) {
                $this->em->remove($managed);
            }
        }
        $this->em->flush();
        parent::tearDown();
    }

    private function chunks(): AiAssistantDocumentChunkRepository
    {
        return static::getContainer()->get(AiAssistantDocumentChunkRepository::class);
    }

    private function service(): AiAssistantIngestionService
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) {
            ++$this->geminiCalls;
            $this->geminiBodies[] = (string) ($options['body'] ?? '');

            return new MockResponse((string) json_encode([
                'candidates' => [['content' => ['parts' => [['text' => $this->geminiSummary]]]]],
                'usageMetadata' => ['totalTokenCount' => 10],
            ]));
        });

        return new AiAssistantIngestionService(
            $this->em,
            new GeminiIngestionClient($http, new NullLogger(), 'cle-de-test', 'modele-de-test'),
            $this->chunks(),
            new NullLogger(),
        );
    }

    private function project(ProjectStatusEnum $status): Project
    {
        $owner = (new User())->setEmail('ingest-'.bin2hex(random_bytes(4)).'@example.test')->setPassword('x');
        $this->em->persist($owner);
        $project = (new Project())
            ->setTitle('Projet client confidentiel')
            ->setDescription('Message du visiteur : nous préparons un rachat de notre concurrent.')
            ->setLink('')
            ->setOwner($owner)
            ->setStatus($status);
        $project->setSlug('ingest-'.bin2hex(random_bytes(4)));
        $this->em->persist($project);
        $this->em->flush();
        $this->created[] = $owner;
        $this->created[] = $project;

        return $project;
    }

    /** @return iterable<string, array{ProjectStatusEnum}> */
    public static function hiddenStatuses(): iterable
    {
        foreach (ProjectStatusEnum::cases() as $status) {
            if (!\in_array($status, [ProjectStatusEnum::IN_PROGRESS, ProjectStatusEnum::COMPLETED], true)) {
                yield $status->value => [$status];
            }
        }
    }

    #[DataProvider('hiddenStatuses')]
    public function testHiddenProjectIsNeverSentToGeminiNorExposedToTheAssistant(ProjectStatusEnum $status): void
    {
        $project = $this->project($status);
        // Chunk hérité d'avant le correctif : il doit disparaître.
        $this->em->persist((new AiAssistantDocumentChunk())
            ->setEntityType('Project')->setEntityId((int) $project->getId())->setChunkIndex(0)
            ->setChunkText('ancien')->setChunkSummary('ancien')->setMetadata(['is_public' => true, 'label' => 'x']));
        $this->em->flush();

        $this->service()->ingest('Project', (int) $project->getId());

        self::assertSame(0, $this->geminiCalls, 'Aucun appel à Gemini pour un projet non public.');
        self::assertSame([], $this->chunks()->findByEntity('Project', (int) $project->getId()));
    }

    public function testPublicProjectIsStillIngested(): void
    {
        $project = $this->project(ProjectStatusEnum::IN_PROGRESS);

        $this->service()->ingest('Project', (int) $project->getId());

        self::assertSame(1, $this->geminiCalls);
        self::assertCount(1, $this->chunks()->findByEntity('Project', (int) $project->getId()));
    }

    /**
     * Zéro information de configuration (2026-11-26) : la pile technique d'un
     * projet décrit un système déployé — elle n'est jamais envoyée à l'IA.
     */
    public function testTechStackIsNeverSentToTheAi(): void
    {
        $project = $this->project(ProjectStatusEnum::IN_PROGRESS);
        $info = (new \App\Entity\ProjectInfo())
            ->setRole('Architecte')
            ->setTechStack([['name' => 'Infrastructure — Traefik, Cloudflare Access', 'reason' => 'WAF']]);
        $project->setInfo($info);
        $this->em->persist($info);
        $this->em->flush();
        $this->created[] = $info;

        $this->service()->ingest('Project', (int) $project->getId());

        self::assertSame(1, $this->geminiCalls);
        $sent = implode("\n", $this->geminiBodies);
        self::assertStringContainsString('Architecte', $sent, 'Le reste de la fiche est bien ingéré.');
        self::assertStringNotContainsString('Traefik', $sent);
        self::assertStringNotContainsString('Stack technique', $sent);
    }

    /**
     * Contre-audit 5dd6646 : description en texte brut, consigne Gemini sans
     * configuration ni nom de client, et toute phrase du résumé qui nommerait
     * la configuration retirée avant stockage.
     */
    public function testIngestedSummaryNeverCarriesSecurityConfiguration(): void
    {
        $project = $this->project(ProjectStatusEnum::IN_PROGRESS);
        $project->setDescription('<h2>Le projet</h2><p>Un portail <strong>bilingue</strong> &amp; sûr.</p>');
        $this->em->flush();
        $this->geminiSummary = 'Un portail bilingue. Protégé par Cloudflare Access et Traefik. Les demandes arrivent cadrées.';

        $this->service()->ingest('Project', (int) $project->getId());

        $sent = json_decode((string) end($this->geminiBodies), true);
        self::assertStringEndsWith("Le projet Un portail bilingue & sûr.", $sent['contents'][0]['parts'][0]['text']);
        $instruction = $sent['systemInstruction']['parts'][0]['text'];
        self::assertStringContainsString("N'y mets JAMAIS", $instruction);
        self::assertStringContainsString("le nom d'un client", $instruction);
        self::assertStringNotContainsString('technologies, résultats', $instruction, 'Plus d\'injonction à garder les technologies.');
        $chunks = $this->chunks()->findByEntity('Project', (int) $project->getId());
        self::assertSame('Un portail bilingue. Les demandes arrivent cadrées.', $chunks[0]->getChunkSummary());
    }

    public function testDeletedProjectLeavesTheAssistant(): void
    {
        $project = $this->project(ProjectStatusEnum::IN_PROGRESS);
        $id = (int) $project->getId();
        $this->service()->ingest('Project', $id);
        self::assertCount(1, $this->chunks()->findByEntity('Project', $id));

        /** @var \Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $transport->reset();

        // Suppression réelle (comme AdminProjectController::delete) : le
        // subscriber doit mettre en file une réingestion de ce projet.
        $this->created = array_values(array_filter($this->created, static fn (object $e): bool => $e !== $project));
        $this->em->remove($project);
        $this->em->flush();

        $queued = array_filter(
            $transport->getSent(),
            static fn (\Symfony\Component\Messenger\Envelope $e): bool => $e->getMessage() instanceof \App\Message\AiAssistantIngestMessage
                && 'Project' === $e->getMessage()->entityType && $id === $e->getMessage()->entityId,
        );
        self::assertCount(1, $queued, 'La suppression doit déclencher la purge des chunks.');

        $this->service()->ingest('Project', $id);
        self::assertSame([], $this->chunks()->findByEntity('Project', $id));
    }

    public function testEmptiedContentLeavesTheAssistant(): void
    {
        $project = $this->project(ProjectStatusEnum::IN_PROGRESS);
        $id = (int) $project->getId();
        $this->service()->ingest('Project', $id);
        self::assertCount(1, $this->chunks()->findByEntity('Project', $id));

        $project->setTitle('')->setDescription('');
        $this->em->flush();
        $this->service()->ingest('Project', $id);

        self::assertSame([], $this->chunks()->findByEntity('Project', $id));
    }

    public function testPurgeOrphansCommandRemovesChunksOfDeletedEntitiesOnly(): void
    {
        $kept = $this->project(ProjectStatusEnum::IN_PROGRESS);
        $this->service()->ingest('Project', (int) $kept->getId());
        // Identifiant aléatoire et nettoyage préalable : le test ne dépend
        // d'aucun résidu d'une exécution précédente.
        $orphanId = random_int(900000000, 999999999);
        $this->chunks()->deleteByEntity('Project', $orphanId);
        $this->em->persist((new AiAssistantDocumentChunk())
            ->setEntityType('Project')->setEntityId($orphanId)->setChunkIndex(0)
            ->setChunkText('projet supprimé')->setChunkSummary('projet supprimé')->setMetadata(['is_public' => true, 'label' => 'x']));
        $this->em->flush();
        $callsBefore = $this->geminiCalls;

        $command = new \App\Command\AiAssistantReingestCommand($this->service(), $this->em);
        $tester = new \Symfony\Component\Console\Tester\CommandTester($command);
        $tester->execute(['--purge-orphans' => true]);

        $tester->assertCommandIsSuccessful();
        self::assertSame([], $this->chunks()->findByEntity('Project', $orphanId));
        self::assertCount(1, $this->chunks()->findByEntity('Project', (int) $kept->getId()));
        self::assertSame($callsBefore, $this->geminiCalls, 'La purge n\'appelle jamais Gemini.');
        $this->chunks()->deleteByEntity('Project', $orphanId);
        $this->em->flush();
    }

    public function testProjectThatBecomesHiddenLeavesTheAssistant(): void
    {
        $project = $this->project(ProjectStatusEnum::COMPLETED);
        $this->service()->ingest('Project', (int) $project->getId());
        self::assertCount(1, $this->chunks()->findByEntity('Project', (int) $project->getId()));

        $project->setStatus(ProjectStatusEnum::SUSPENDED);
        $this->em->flush();
        $this->service()->ingest('Project', (int) $project->getId());

        self::assertSame([], $this->chunks()->findByEntity('Project', (int) $project->getId()));
    }
}
