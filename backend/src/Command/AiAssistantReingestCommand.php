<?php

namespace App\Command;

use App\Entity\Article;
use App\Entity\Experience;
use App\Entity\Project;
use App\Entity\SkillCategory;
use App\Service\AiAssistantIngestionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Force une (ré)ingestion RAG, en synchrone (contrairement au déclenchement
 * automatique via Messenger sur création/modification) — utile après un
 * changement du prompt de résumé, une migration de modèle Gemini, ou pour
 * peupler la base avant la toute première mise en ligne du chat (cf. §3.1 du
 * document d'architecture assistant IA).
 *
 *   php bin/console app:assistant:reingest --all
 *   php bin/console app:assistant:reingest --entity=Project --id=42
 */
#[AsCommand(name: 'app:assistant:reingest', description: 'Force la réingestion RAG du contenu du portfolio pour l\'assistant IA.')]
class AiAssistantReingestCommand extends Command
{
    private const ENTITY_CLASSES = [
        'Project' => Project::class,
        'Article' => Article::class,
        'Experience' => Experience::class,
        'SkillCategory' => SkillCategory::class,
    ];

    public function __construct(
        private readonly AiAssistantIngestionService $ingestionService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('all', null, InputOption::VALUE_NONE, 'Réingère tout le contenu (Project + Article + Experience + SkillCategory).')
            ->addOption('entity', null, InputOption::VALUE_REQUIRED, 'Type d\'entité : Project, Article, Experience ou SkillCategory.')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Id de l\'entité (requis avec --entity).')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('all')) {
            return $this->reingestAll($io);
        }

        $entityType = $input->getOption('entity');
        $id = $input->getOption('id');

        if (null === $entityType || null === $id) {
            $io->error('Utiliser --all, ou --entity=<Project|Article|Experience> --id=<id>.');

            return Command::INVALID;
        }

        if (!isset(self::ENTITY_CLASSES[$entityType])) {
            $io->error(sprintf('Type d\'entité inconnu : "%s". Valeurs possibles : %s.', $entityType, implode(', ', array_keys(self::ENTITY_CLASSES))));

            return Command::INVALID;
        }

        $this->ingestionService->ingest($entityType, (int) $id);
        $io->success(sprintf('%s #%d réingéré(e).', $entityType, (int) $id));

        return Command::SUCCESS;
    }

    private function reingestAll(SymfonyStyle $io): int
    {
        $total = 0;

        foreach (self::ENTITY_CLASSES as $entityType => $class) {
            $ids = array_map(
                static fn (object $entity): int => (int) $entity->getId(),
                $this->entityManager->getRepository($class)->findAll(),
            );

            $io->section(sprintf('%s (%d)', $entityType, \count($ids)));
            foreach ($ids as $id) {
                $this->ingestionService->ingest($entityType, $id);
                $io->writeln(sprintf('  #%d ok', $id));
                ++$total;
            }
        }

        $io->success(sprintf('%d entité(s) réingérée(s).', $total));

        return Command::SUCCESS;
    }
}
