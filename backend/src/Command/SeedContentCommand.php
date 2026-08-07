<?php

namespace App\Command;

use App\Repository\AboutContentRepository;
use App\Repository\HomeContentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Seed le contenu réel des pages Accueil et À propos (HomeContent/
 * AboutContent).
 *
 * Remplace l'étape manuelle "ouvrir /admin/content/home puis
 * /admin/content/about dans le back-office avant le premier build frontend"
 * (cf. infra/README.md) : ces deux pages lancent volontairement une erreur
 * de build si leur ligne de contenu est absente en base (cf. commentaires
 * dans ces pages Next.js), et HomeContentRepository::getContent() /
 * AboutContentRepository::getContent() ne créent qu'une ligne VIDE à la
 * demande — cette commande y écrit directement le contenu réel, en une
 * étape scriptable et rejouable dans le runbook de déploiement.
 *
 * Idempotente par défaut : si le contenu a déjà été personnalisé (hors
 * valeurs vides posées par getContent()), la commande ne l'écrase pas, sauf
 * --force. Le contenu reste ensuite modifiable normalement via le
 * back-office — cette commande ne fait que poser la première version.
 */
#[AsCommand(name: 'app:seed-content', description: "Seed le contenu réel des pages Accueil et À propos (HomeContent/AboutContent).")]
class SeedContentCommand extends Command
{
    public function __construct(
        private readonly HomeContentRepository $homeContentRepository,
        private readonly AboutContentRepository $aboutContentRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Écrase un contenu déjà personnalisé (par défaut, une ligne non vide est laissée intacte).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        $home = $this->homeContentRepository->getContent();
        if ('' !== $home->getHeroTitle() && !$force) {
            $io->comment('HomeContent déjà personnalisé — ignoré (--force pour écraser).');
        } else {
            $this->seedHomeContent($home);
            $io->success('HomeContent seedé.');
        }

        $about = $this->aboutContentRepository->getContent();
        if ('' !== $about->getHeroTitle() && !$force) {
            $io->comment('AboutContent déjà personnalisé — ignoré (--force pour écraser).');
        } else {
            $this->seedAboutContent($about);
            $io->success('AboutContent seedé.');
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }

    private function seedHomeContent(\App\Entity\HomeContent $c): void
    {
        $c->setHeroEyebrow('Disponible pour vos projets')
            ->setHeroEyebrowEn('Available for your projects')
            ->setHeroTitle('Je conçois des solutions numériques')
            ->setHeroTitleEn('I build digital solutions')
            ->setHeroTitleAccent('sûres, robustes et sur mesure.')
            ->setHeroTitleAccentEn('secure, robust and tailor-made.')
            ->setHeroSub("Développeur FullStack, consultant en cybersécurité et fondateur de Digital Business Group. Je conçois, sécurise et fais évoluer des applications web et mobiles pensées pour durer.")
            ->setHeroSubEn('FullStack developer, cybersecurity consultant and founder of Digital Business Group. I design, secure and evolve web and mobile applications built to last.')
            ->setHeroRoles(['Développeur FullStack', 'Consultant en Cybersécurité', 'Intégrateur de solutions IA', 'Web Designer'])
            ->setHeroRolesEn(['FullStack Developer', 'Cybersecurity Consultant', 'AI Solutions Integrator', 'Web Designer'])
            ->setFounderBadge('Fondateur & Directeur Général — Digital Business Group')
            ->setFounderBadgeEn('Founder & CEO — Digital Business Group')
            ->setDiagramCaption("Architecture réelle de ce site : Symfony, API Platform, Next.js et l'assistant IA partagent la même source de vérité.")
            ->setDiagramCaptionEn("This site's real architecture: Symfony, API Platform, Next.js and the AI assistant share the same source of truth.")
            ->setAboutTitle('Un profil multidisciplinaire, une exigence unique')
            ->setAboutTitleEn('A multidisciplinary profile, one standard')
            ->setAboutP1("Depuis 2016, je conçois des solutions web et mobiles pour des entreprises exigeantes, en m'appuyant sur une double expertise rare : le développement logiciel et la cybersécurité. Mon parcours dans l'assurance m'a aussi appris à raisonner en termes de risque, pas seulement de code.")
            ->setAboutP1En("Since 2016, I've built web and mobile solutions for demanding businesses, drawing on a rare dual expertise: software development and cybersecurity. My background in insurance also taught me to reason in terms of risk, not just code.")
            ->setAboutP2("Fondateur de Digital Business Group, j'accompagne aujourd'hui mes clients de la conception à la mise en production, en intégrant systématiquement la sécurité et l'intelligence artificielle dès la conception.")
            ->setAboutP2En('As founder of Digital Business Group, I now support clients from design through to production, systematically building in security and AI from day one.')
            ->setAboutHighlightTitle('Sécurité et performance, dès la conception')
            ->setAboutHighlightTitleEn('Security and performance, by design')
            ->setAboutHighlightDesc("Chaque projet est pensé avec les mêmes exigences qu'un audit de cybersécurité : durcissement, surveillance, sauvegardes — posés dès le départ, pas ajoutés après coup.")
            ->setAboutHighlightDescEn('Every project is held to the same standard as a security audit: hardening, monitoring, backups — built in from the start, not bolted on afterward.')
            ->setAboutVisionText("Une architecture bien pensée aujourd'hui évite les refontes coûteuses demain. Je conçois des systèmes qui grandissent avec l'activité de mes clients, sans dette technique cachée.")
            ->setAboutVisionTextEn("A well-thought-out architecture today avoids costly rewrites tomorrow. I design systems that grow with my clients' business, without hidden technical debt.")
            ->setAboutMissionText("Accompagner durablement la croissance de mes clients en leur livrant des solutions sur mesure, sécurisées et évolutives — de l'idée jusqu'à l'exploitation en production.")
            ->setAboutMissionTextEn("Supporting my clients' long-term growth with tailor-made, secure and scalable solutions — from idea through to production.")
            ->setFreelanceTitle("Besoin d'un développeur qui pense aussi sécurité ?")
            ->setFreelanceTitleEn('Need a developer who thinks security too?')
            ->setFreelanceLede("Je conçois, développe et sécurise votre application web ou mobile — architecture, intégration d'IA et durcissement inclus, pas en option.")
            ->setFreelanceLedeEn('I design, build and secure your web or mobile application — architecture, AI integration and hardening included, not optional extras.')
            ->setFreelancePoint1('Développement FullStack (Symfony, API Platform, Next.js) et mobile (Flutter)')
            ->setFreelancePoint1En('FullStack development (Symfony, API Platform, Next.js) and mobile (Flutter)')
            ->setFreelancePoint2('Cybersécurité intégrée dès la conception : durcissement, audit, surveillance')
            ->setFreelancePoint2En('Cybersecurity built in from day one: hardening, audit, monitoring')
            ->setFreelancePoint3('Intégration de solutions IA sur mesure (assistants, automatisations)')
            ->setFreelancePoint3En('Tailor-made AI integration (assistants, automation)')
            ->setFreelanceCardDesc("Un point de contact unique, du cahier des charges à la mise en production — sans sous-traitance cachée.")
            ->setFreelanceCardDescEn('A single point of contact, from spec to production — no hidden subcontracting.')
            ->setContactCtaTitle('Parlons de votre projet')
            ->setContactCtaTitleEn("Let's talk about your project")
            ->setContactCtaSub('Que ce soit pour une refonte, une nouvelle application ou un audit de sécurité, je réponds sous 48h.')
            ->setContactCtaSubEn('Whether it’s a redesign, a new application or a security audit, I reply within 48 hours.')
        ;
    }

    private function seedAboutContent(\App\Entity\AboutContent $c): void
    {
        $c->setHeroEyebrow('Développeur, entrepreneur, consultant en cybersécurité')
            ->setHeroEyebrowEn('Developer, entrepreneur, cybersecurity consultant')
            ->setHeroTitle('Derrière chaque ligne de code,')
            ->setHeroTitleEn('Behind every line of code,')
            ->setHeroTitleAccent('une exigence de rigueur et de sécurité.')
            ->setHeroTitleAccentEn('a standard of rigor and security.')
            ->setHeroSub("Je m'appelle Horlynat MAMPASSI MBAMA. Depuis Brazzaville, j'accompagne des entreprises dans la conception de solutions numériques robustes, sécurisées et pensées pour durer.")
            ->setHeroSubEn("I'm Horlynat MAMPASSI MBAMA. From Brazzaville, I help businesses design digital solutions that are robust, secure and built to last.")
            ->setProfileName('Horlynat MAMPASSI MBAMA')
            ->setProfileRole('Développeur FullStack & Consultant Cybersécurité')
            ->setProfileRoleEn('FullStack Developer & Cybersecurity Consultant')
            ->setProfileAvailability('Ouvert aux missions freelance')
            ->setProfileAvailabilityEn('Open to freelance work')
            ->setProfileAlso('Fondateur — Digital Business Group')
            ->setProfileAlsoEn('Founder — Digital Business Group')
            ->setProfileLocation('Brazzaville, Congo')
            ->setProfileLocationEn('Brazzaville, Congo')
            ->setProfileWorkMode('Télétravail ou présentiel')
            ->setProfileWorkModeEn('Remote or on-site')
            ->setProfileLanguages('Français, Anglais')
            ->setProfileLanguagesEn('French, English')
            ->setBioTitle('Un parcours entre technique, sécurité et entrepreneuriat')
            ->setBioTitleEn('A path between engineering, security and entrepreneurship')
            ->setBioP1("Titulaire d'une licence en mathématiques et formé au développement web intégrateur, j'ai construit mon parcours à la croisée de plusieurs disciplines : l'assurance, où j'ai passé près de huit ans à gérer des risques complexes, et le développement web, ma véritable passion depuis 2016.")
            ->setBioP1En("With a degree in mathematics and training as a web integrator developer, I've built my career at the crossroads of several disciplines: insurance, where I spent nearly eight years managing complex risk, and web development, my real passion since 2016.")
            ->setBioP2("En 2018, j'ai pris la responsabilité informatique d'une entreprise, où j'ai conçu des solutions digitales sur mesure tout en assurant la sécurisation des actifs numériques — c'est là qu'est né mon intérêt pour la cybersécurité, que j'ai depuis approfondi en consultant.")
            ->setBioP2En("In 2018, I took over IT responsibility at a company, designing tailor-made digital solutions while securing its digital assets — that's where my interest in cybersecurity was born, and I've since deepened it as a consultant.")
            ->setBioP3("Aujourd'hui, je dirige Digital Business Group, l'entreprise que j'ai fondée pour réunir ces expertises : développement FullStack, intégration de solutions IA, cybersécurité et web design, au service de projets qui doivent tenir dans la durée.")
            ->setBioP3En("Today, I run Digital Business Group, the company I founded to bring these expertises together: FullStack development, AI integration, cybersecurity and web design, in service of projects built to last.")
            ->setVisionTitle('Ma vision du métier')
            ->setVisionTitleEn('My take on the craft')
            ->setVisionLede("Le développement web ne s'arrête pas au code qui fonctionne — il doit résister dans le temps, à la charge et aux attaques.")
            ->setVisionLedeEn('Web development doesn’t stop at code that works — it has to hold up over time, under load, and against attacks.')
            ->setVisionTodayText("Trop de projets sont livrés fonctionnels mais fragiles : sécurité en option, dette technique invisible, aucune supervision une fois en production.")
            ->setVisionTodayTextEn('Too many projects ship functional but fragile: security as an afterthought, invisible technical debt, no monitoring once in production.')
            ->setVisionTomorrowText("Je conçois des solutions où la sécurité, la scalabilité et la maintenabilité sont pensées dès le premier commit, pas ajoutées dans l'urgence après un incident.")
            ->setVisionTomorrowTextEn('I design solutions where security, scalability and maintainability are considered from the first commit — not rushed in after an incident.')
            ->setWhy1Title('Double expertise dev + sécurité')
            ->setWhy1TitleEn('Dual expertise: dev + security')
            ->setWhy1Desc("Je ne me contente pas de coder une fonctionnalité, je réfléchis systématiquement à sa surface d'attaque et à sa résilience.")
            ->setWhy1DescEn("I don't just code a feature — I systematically think through its attack surface and resilience.")
            ->setWhy2Title("Rigueur héritée de l'assurance")
            ->setWhy2TitleEn('Rigor inherited from insurance')
            ->setWhy2Desc("Huit ans à évaluer et gérer des risques m'ont appris à anticiper ce qui peut mal tourner — une discipline que j'applique à chaque architecture technique.")
            ->setWhy2DescEn('Eight years assessing and managing risk taught me to anticipate what can go wrong — a discipline I apply to every technical architecture.')
            ->setWhy3Title('IA intégrée, pas plaquée')
            ->setWhy3TitleEn('AI integrated, not bolted on')
            ->setWhy3Desc("J'intègre l'intelligence artificielle comme un outil au service du produit — automatisations, assistants — jamais comme un argument marketing.")
            ->setWhy3DescEn('I integrate AI as a tool in service of the product — automation, assistants — never as a marketing buzzword.')
            ->setWhy4Title('Sens du design, pas seulement du code')
            ->setWhy4TitleEn('An eye for design, not just code')
            ->setWhy4Desc("Formé au web design et à la conception graphique, je livre des interfaces soignées, pas seulement des API qui répondent.")
            ->setWhy4DescEn('Trained in web design and graphic design, I deliver polished interfaces — not just APIs that respond.')
            ->setBeyondLanguages(['Français', 'Anglais'])
            ->setBeyondLanguagesEn(['French', 'English'])
            ->setBeyondInterests(['Direction de chœur (SATB)', 'Veille en cybersécurité', 'Intelligence artificielle', 'Design graphique'])
            ->setBeyondInterestsEn(['Choir conducting (SATB)', 'Cybersecurity watch', 'Artificial intelligence', 'Graphic design'])
            ->setCtaTitle('Un projet en tête ?')
            ->setCtaTitleEn('Got a project in mind?')
            ->setCtaSub("Discutons de vos besoins — développement, sécurisation ou intégration d'IA, je vous accompagne de A à Z.")
            ->setCtaSubEn('Let’s talk about your needs — development, security or AI integration, I’ll guide you from A to Z.')
        ;
    }
}
