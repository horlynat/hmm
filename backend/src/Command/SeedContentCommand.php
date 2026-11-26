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
        // Textes de la page : ceux du prototype validé (source unique,
        // cf. app:about:apply-prototype) ; le reste ci-dessous.
        \App\Command\AboutApplyPrototypeCommand::applyTo($c);
        $c->setSkillsTitle('Quatre domaines, un même niveau d’exigence')
            ->setSkillsTitleEn('Four fields, one standard of excellence')
            ->setSkillsLede('Choisissez un domaine : ce que je fais pour vous, mes outils du moment, les offres et la preuve associées.')
            ->setSkillsLedeEn('Pick a field: what I do for you, my current tools, and the related services and proof.')
            ->setSkillsNote('Liste indicative : j’apprends en continu les technologies que chaque projet exige.')
            ->setSkillsNoteEn('Indicative list: I keep learning the technologies each project requires.')
            ->setProjectsTitle('Des plateformes conçues, livrées et exploitées')
            ->setProjectsTitleEn('Platforms designed, delivered and run')
            ->setProjectsLede('Chaque réalisation est en service ou en cours de déploiement. Ouvrez une étude de cas pour en comprendre la démarche.')
            ->setProjectsLedeEn('Every project is live or being rolled out. Open a case study to understand the approach.')
            ->setPathTitle('Des mathématiques à l\'entreprise')
            ->setPathTitleEn('From mathematics to business')
            ->setPathLede('Informatique, assurance puis entrepreneuriat : un parcours qui m\'a appris à construire et à anticiper les risques.')
            ->setPathLedeEn('Computing, insurance, then entrepreneurship: a path that taught me to build and to anticipate risks.')
            ->setLearningText('Chaque projet m’amène à apprendre : nouvelles technologies, nouvelles menaces, nouvelles réglementations. Objectif à terme : un doctorat consacré à l’intelligence artificielle.')
            ->setLearningTextEn('Every project teaches me something new: technologies, threats, regulations. Long-term goal: a doctorate in artificial intelligence.')
            ->setJourneyStats([
                ['value' => 'Depuis 2016', 'label' => 'dans le numérique'],
                ['value' => 'Depuis 2018', 'label' => 'en gestion et management des risques'],
                ['value' => '2026', 'label' => 'fondation de Digital Business Group'],
            ])
            ->setJourneyStatsEn([
                ['value' => 'Since 2016', 'label' => 'in digital'],
                ['value' => 'Since 2018', 'label' => 'in risk management'],
                ['value' => '2026', 'label' => 'founding of Digital Business Group'],
            ])
        ;
    }
}
