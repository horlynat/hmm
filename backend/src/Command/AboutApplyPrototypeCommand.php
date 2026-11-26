<?php

namespace App\Command;

use App\Entity\AboutContent;
use App\Repository\AboutContentRepository;
use App\Repository\TranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Remplit les textes de la page À propos avec ceux du prototype validé
 * (« Horlynat | Portail Digital – refonte (prototype) », fonction pAbout,
 * VMA2, POINTS, cta), en français et en anglais. Ensuite, tout se modifie
 * au back-office (Contenu › Page « À propos »).
 *
 * Sans --force : affiche seulement ce qui changerait (aucune écriture).
 * Les expériences, formations, réalisations et domaines de compétence ne
 * sont pas touchés (ils viennent de leurs propres écrans).
 */
#[AsCommand(name: 'app:about:apply-prototype', description: 'Remplit la page À propos avec les textes du prototype (FR + EN).')]
final class AboutApplyPrototypeCommand extends Command
{
    /** Champ => [français (prototype), anglais]. */
    public const TEXTS = [
        'heroTitle' => ['Ingénieur et', 'Software engineer'],
        'heroTitleAccent' => ['architecte logiciel.', 'and architect.'],
        'heroSub' => [
            'Consultant en cybersécurité, gestion et management des risques. Depuis Brazzaville, je conçois et je sécurise les outils numériques des entreprises, sur place ou à distance.',
            'Cybersecurity and risk management consultant. From Brazzaville, I design and secure the digital tools of businesses, on site or remotely.',
        ],
        'profileRole' => ['Ingénieur et architecte logiciel', 'Software engineer and architect'],
        'profileAvailability' => ['Disponible', 'Available'],
        'visionLede' => [
            'Penser au risque avant de construire, pour que chaque organisation dispose d’outils numériques aussi sûrs que ceux des grands groupes.',
            'Think about risk before building, so that every organisation has digital tools as safe as those of large groups.',
        ],
        'visionPillar' => [
            'Une architecture bien pensée aujourd’hui évite les refontes coûteuses demain. Je conçois des systèmes qui grandissent avec l’activité de mes clients, sans dette technique cachée.',
            'A well-designed architecture today avoids costly rebuilds tomorrow. I design systems that grow with my clients’ business, with no hidden technical debt.',
        ],
        'missionPillar' => [
            'Accompagner durablement la croissance de mes clients en leur livrant des solutions sur mesure, sécurisées et évolutives, de l’idée jusqu’à l’exploitation.',
            'Support my clients’ growth over the long term by delivering tailor-made, secure and scalable solutions, from the idea to day-to-day operation.',
        ],
        'ambitionPillar' => [
            'Contribuer, projet après projet, à ce que les organisations disposent d’outils numériques fiables, sûrs et durables, et accompagner leur croissance sur le long terme.',
            'Help organisations, project after project, to have reliable, safe and lasting digital tools, and support their growth over the long term.',
        ],
        'visionTodayText' => [
            'Permettre à chaque commerce, entreprise ou institution d’échanger avec ses clients et ses usagers à tout moment, sans perdre de temps.',
            'Enable every shop, business or institution to talk with its customers and users at any time, without wasting time.',
        ],
        'horizonMidText' => [
            'Faire de la sécurité et de la gestion des risques un réflexe pour les PME d’Afrique centrale.',
            'Make security and risk management a reflex for SMEs in Central Africa.',
        ],
        'visionTomorrowText' => [
            'Faire du Congo une référence africaine en ingénierie logicielle sûre, et former la relève.',
            'Make Congo an African reference in secure software engineering, and train the next generation.',
        ],
        'bioTitle' => ['Je pense au risque avant de construire', 'I think about risk before I build'],
        'bioP1' => [
            'Une formation en mathématiques, huit ans à évaluer des risques dans l\'assurance, et depuis 2016 la conception de solutions web et mobiles : c\'est cette trajectoire qui façonne ma double expertise, le développement logiciel et la cybersécurité.',
            'A background in mathematics, eight years assessing risks in insurance, and since 2016 the design of web and mobile solutions: this path shapes my dual expertise in software development and cybersecurity.',
        ],
        'bioP2' => [
            'Fondateur de Digital Business Group, j\'accompagne mes clients de la conception à la mise en production, en intégrant la sécurité et l\'intelligence artificielle dès le départ.',
            'As the founder of Digital Business Group, I support my clients from design to production, building in security and artificial intelligence from the start.',
        ],
        'why1Title' => ['Sécurité et performance, dès la conception', 'Security and performance, by design'],
        'why1Desc' => [
            'Durcissement, surveillance, sauvegardes : posés dès le départ, pas ajoutés après coup.',
            'Hardening, monitoring, backups: built in from the start, not bolted on afterwards.',
        ],
        'why2Title' => ['Double expertise développement et sécurité', 'Dual expertise in development and security'],
        'why2Desc' => [
            'Je réfléchis systématiquement à la surface d\'attaque et à la résilience de chaque fonctionnalité.',
            'I systematically think about the attack surface and resilience of every feature.',
        ],
        'why3Title' => ['Rigueur héritée de l\'assurance', 'Rigour inherited from insurance'],
        'why3Desc' => [
            'Huit ans à évaluer des risques m\'ont appris à anticiper ce qui peut mal tourner.',
            'Eight years assessing risks taught me to anticipate what can go wrong.',
        ],
        'why4Title' => ['Architectures prêtes pour l\'IA', 'AI-ready architectures'],
        'why4Desc' => [
            'Pensées dès le départ pour accueillir automatisations et assistants, jamais plaqués après coup.',
            'Designed from the start to host automations and assistants, never bolted on afterwards.',
        ],
        'why5Title' => ['Sens du design, pas seulement du code', 'An eye for design, not just code'],
        'why5Desc' => [
            'Formé au web design : des interfaces soignées, pas seulement des API qui répondent.',
            'Trained in web design: polished interfaces, not just APIs that respond.',
        ],
        'ctaTitle' => ['Réalisons votre projet ensemble', 'Let’s build your project together'],
        'ctaSub' => [
            'Mission, audit ou projet sur mesure : décrivez-moi votre besoin, et nous définissons ensemble la meilleure façon de le mener à bien.',
            'Assignment, audit or custom project: tell me about your need, and together we will define the best way to carry it out.',
        ],
    ];

    /** Nom sous la photo (sans traduction). */
    public const PROFILE_NAME = 'Horlynat Mampassi Mbama';

    public function __construct(
        private readonly AboutContentRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly TranslationRepository $translations,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Écrit réellement les textes (sinon : aperçu seulement).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        // Aperçu en lecture seule : sans ligne en base, tout est « à changer » et
        // rien n'est créé ; la ligne n'est créée qu'avec --force.
        $content = $this->repository->findFirst() ?? new AboutContent();

        $changes = [];
        foreach (self::TEXTS as $field => [$fr, $en]) {
            $base = ucfirst($field);
            foreach (['' => $fr, 'En' => $en] as $suffix => $value) {
                $current = $content->{'get'.$base.$suffix}();
                if ($current !== $value) {
                    $changes[] = [$field.$suffix, (string) $current, $value];
                }
            }
        }
        if ($content->getProfileName() !== self::PROFILE_NAME) {
            $changes[] = ['profileName', $content->getProfileName(), self::PROFILE_NAME];
        }

        if ([] === $changes) {
            $io->success('La page À propos contient déjà les textes du prototype.');

            return Command::SUCCESS;
        }

        $io->table(['Champ', 'Actuel', 'Prototype'], array_map(
            static fn (array $c): array => [$c[0], mb_strimwidth($c[1], 0, 60, '…'), mb_strimwidth($c[2], 0, 60, '…')],
            $changes,
        ));

        if (!$input->getOption('force')) {
            $io->note(\sprintf('%d champ(s) seraient modifiés. Relancez avec --force pour écrire.', \count($changes)));

            return Command::SUCCESS;
        }

        $content = $this->repository->getContent();
        self::applyTo($content);
        $violations = $this->validator->validate($content);
        if (\count($violations) > 0) {
            $io->error((string) $violations);

            return Command::FAILURE;
        }

        $content->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
        // Champs « En » : table `translation`, écrite après flush (id garanti).
        $this->translations->syncFromEntity($content);
        $io->success(\sprintf('%d champ(s) mis à jour avec les textes du prototype.', \count($changes)));

        return Command::SUCCESS;
    }

    /** Écrit les textes du prototype (FR + EN) dans l'entité, sans flush. */
    public static function applyTo(AboutContent $content): void
    {
        foreach (self::TEXTS as $field => [$fr, $en]) {
            $content->{'set'.ucfirst($field)}($fr);
            $content->{'set'.ucfirst($field).'En'}($en);
        }
        $content->setProfileName(self::PROFILE_NAME);
    }
}
