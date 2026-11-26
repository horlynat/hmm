<?php

namespace App\Command;

use App\Entity\Project;
use App\Entity\SkillDomain;
use App\Repository\OfferRepository;
use App\Repository\ProjectRepository;
use App\Repository\SkillDomainRepository;
use App\Repository\TranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Nettoyage de contenu demandé par le propriétaire (2026-11-26), sans saisie
 * manuelle dans l'admin :
 *
 *  1. Fiche « Horlynat | Portail Digital » : retire toute information de
 *     configuration de sécurité (fournisseurs, proxy, rôles internes, entités,
 *     bibliothèques, supervision, versions, champ anti-spam) de la description
 *     (FR + EN), des lignes de pile technique et du défi n° 4. Remplacements
 *     CHIRURGICAUX : seuls les passages exacts connus sont remplacés ; un passage
 *     déjà modifié à la main est laissé tel quel et signalé.
 *  2. Domaines de compétence : remplace les identifiants d'offres et de
 *     réalisation hérités du prototype (« application », « dbg »…) par les
 *     vrais identifiants publiés.
 *
 * Sans --force : aperçu seulement, aucune écriture. Idempotente.
 */
#[AsCommand(name: 'app:content:cleanup', description: 'Retire la configuration de sécurité de la fiche Portail Digital et relie les domaines aux vraies offres.')]
final class ContentCleanupCommand extends Command
{
    public const PROJECT_SLUG = 'horlynat-portail-digital';

    /** @var list<array{0: string, 1: string}> Passages de la description FR : [actuel, nettoyé]. */
    public const DESCRIPTION_FR = [
        ['<p>Le projet est un monorepo doté de trois branches pérennes, intégrées exclusivement via des pull requests — aucun push direct en production :</p><ul><li><code><strong>backend/</strong></code> → Symfony 8.1 / PHP 8.4, API Platform 4, MySQL 8</li><li><code><strong>frontend/</strong></code> → Next.js 16 (App Router), React 19, TypeScript</li><li><code><strong>infra/</strong></code> → Docker Compose, Traefik, GitHub Actions, scripts VPS</li></ul><p></p><p>Le frontend n&#39;effectue aucun appel direct côté client vers l&#39;API : tout passe par le serveur (Server Components / Server Actions). Conséquence directe — aucune clé d&#39;API n&#39;est jamais exposée dans le navigateur, et le site public ne comporte aucun compte utilisateur à protéger.</p>',
         '<p>Le projet est un monorepo intégré exclusivement par pull requests, sans aucun push direct en production : un backend Symfony / API Platform, un frontend Next.js et une infrastructure conteneurisée, déployée automatiquement après les tests et un contrôle de sécurité de l\'image.</p><p>Le navigateur ne parle jamais directement à l\'API : tout passe par le serveur. Aucune clé d\'API n\'est donc exposée côté client.</p>'],
        ['<li>Un niveau de responsabilité gradué par personne (<code>ROLE_USER</code>, <code>ROLE_EDITOR</code>, <code>ROLE_MODERATOR</code>), attribué selon ce qui lui est confié sur chaque projet — pas un accès uniforme pour tous les collaborateurs</li>',
         '<li>Un niveau de responsabilité gradué par personne, attribué selon ce qui lui est confié sur chaque projet, et non un accès uniforme pour tous les collaborateurs</li>'],
        ['<p>Cet espace s&#39;appuie sur le même modèle de données que le reste du portail (<code>ProjectExpense</code>, <code>ProjectHistory</code>, <code>ProjectTask</code>, <code>TimeEntry</code>, <code>Invoice</code>, <code>SupportTicket</code>) — aucune donnée de projet ou de facturation n&#39;est dupliquée ou ressaisie ailleurs.</p>',
         '<p>Cet espace s\'appuie sur le même modèle de données que le reste du portail : aucune donnée de projet ou de facturation n\'est dupliquée ou ressaisie ailleurs.</p>'],
        ['<p>Le back-office, qui concentre budgets, dépenses et historique clients, est le point le plus protégé du système, avec <strong>quatre couches indépendantes</strong> : périmètre Cloudflare (WAF + trafic limité aux IP Cloudflare), Cloudflare Access en Zero Trust, authentification Symfony, puis 2FA TOTP obligatoire — y compris sur une session restaurée par cookie « remember me ». Son accès est réservé aux rôles internes : <code>ROLE_ADMIN</code> et <code>ROLE_SUPER_ADMIN</code> y ont un accès complet ; <code>ROLE_MANAGER</code> y accède également, mais de façon restreinte, au seul module finance. Les trois rôles restants de la hiérarchie (<code>ROLE_USER</code>, <code>ROLE_EDITOR</code>, <code>ROLE_MODERATOR</code>) n&#39;ouvrent aucun accès au back-office : ils sont attribués aux freelances/pro de l&#39;espace collaboration, en fonction des responsabilités qui leur sont assignées sur chaque projet — un niveau de permission gradué plutôt qu&#39;un accès uniforme. Les clients disposent d&#39;un rôle unique, distinct de cette hiérarchie à six rôles.</p><p></p><p>Le contrôle d&#39;accès applicatif au backend repose sur cette hiérarchie des rôles et<strong> Voters métier</strong> dédiés. Une subtilité volontaire : le rôle le plus élevé du système n&#39;est jamais actif par défaut sur un compte, même présent en base — il exige une élévation explicite à durée limitée, principe de moindre privilège appliqué jusqu&#39;au compte le plus puissant. Les permissions liées à la sécurité elle-même sont, elles, non surchargeables depuis l&#39;interface, pour qu&#39;aucun réglage ne puisse s&#39;élargir tout seul.</p>',
         '<p>Le back-office, qui concentre budgets, dépenses et historique clients, est le point le plus protégé du système : <strong>plusieurs couches de protection indépendantes</strong>, une double authentification obligatoire et un accès réservé à quelques rôles internes, certains limités à un seul module.</p><p></p><p>Chaque utilisateur ne reçoit que les droits nécessaires à son rôle, et les actions sensibles sont tracées. Même le compte le plus puissant n\'a pas ses pleins pouvoirs par défaut : ils s\'activent explicitement, pour une durée limitée. C\'est le principe du moindre privilège, appliqué jusqu\'en haut de la hiérarchie.</p>'],
        ['<p>L&#39;assistant conversationnel repose sur une architecture hybride volontairement isolée du reste de l&#39;application : l&#39;ingestion de contenu (résumé et embedding) tourne en tâche de fond à chaque publication, la conversation en temps réel s&#39;appuie sur une recherche par similarité sur ces mêmes données.</p>',
         '<p>L\'assistant conversationnel est volontairement isolé du reste de l\'application. Il répond à partir des contenus publiés du portfolio : profil, réalisations, articles, offres. Il est protégé contre les tentatives de détournement, et ses réponses sont filtrées avant affichage.</p>'],
    ];

    /** @var list<array{0: string, 1: string}> Passages de la description EN : [actuel, nettoyé]. */
    public const DESCRIPTION_EN = [
        ['<p>The project is a monorepo built on three long-lived branches, integrated exclusively through pull requests — no direct pushes to production:</p><ul><li><code><strong>backend/</strong></code> → Symfony 8.1 / PHP 8.4, API Platform 4, MySQL 8</li><li><code><strong>frontend/</strong></code> → Next.js 16 (App Router), React 19, TypeScript</li><li><code><strong>infra/</strong></code> → Docker Compose, Traefik, GitHub Actions, VPS scripts</li></ul><p></p><p>The frontend makes no direct client-side calls to the API — everything goes through the server (Server Components / Server Actions). Direct consequence: no API key is ever exposed in the browser, and the public site has no user accounts to protect.</p>',
         '<p>The project is a monorepo integrated exclusively through pull requests, with no direct pushes to production: a Symfony / API Platform backend, a Next.js frontend and containerised infrastructure, deployed automatically after the tests and an image security scan.</p><p>The browser never talks to the API directly: everything goes through the server, so no API key is ever exposed client-side.</p>'],
        ['<li>A per-person, graduated level of responsibility (<code>ROLE_USER</code>, <code>ROLE_EDITOR</code>, <code>ROLE_MODERATOR</code>), assigned according to what each person is entrusted with on a given project — not a uniform access level for every collaborator</li>',
         '<li>A per-person, graduated level of responsibility, assigned according to what each person is entrusted with on a given project, not a uniform access level for every collaborator</li>'],
        ['<p>This space runs on the same data model as the rest of the portal (<code>ProjectExpense</code>, <code>ProjectHistory</code>, <code>ProjectTask</code>, <code>TimeEntry</code>, <code>Invoice</code>, <code>SupportTicket</code>) — no project or billing data is duplicated or re-entered anywhere else.</p>',
         '<p>This space runs on the same data model as the rest of the portal: no project or billing data is duplicated or re-entered anywhere else.</p>'],
        ['<p>The back-office, which holds budgets, expenses, and client history, is the most protected point in the system, with four independent layers: the Cloudflare perimeter (WAF + traffic restricted to Cloudflare IPs), Cloudflare Access in Zero Trust mode, Symfony authentication, then mandatory TOTP 2FA — including on a session restored via a &quot;remember me&quot; cookie. Access is reserved for internal roles: <code>ROLE_ADMIN</code> and <code>ROLE_SUPER_ADMIN</code> have full access; <code>ROLE_MANAGER</code> also has access, but restricted to the finance module only. The three remaining roles in the hierarchy (<code>ROLE_USER</code>, <code>ROLE_EDITOR</code>, <code>ROLE_MODERATOR</code>) grant no access to the back-office at all — they&#39;re assigned to freelancers/pros in the collaboration space, based on the responsibilities given to them on each project: a graduated permission level rather than a uniform one. Clients hold a single role, separate from this six-role hierarchy.</p><p>Application-level access control on the backend relies on this role hierarchy and dedicated domain Voters. One deliberate subtlety: the system&#39;s highest role is never active by default on an account, even when present in the database — it requires an explicit, time-limited elevation, a least-privilege principle applied even to the most powerful account. The permissions tied to security itself, meanwhile, cannot be overridden from the interface, so no setting can widen its own scope.</p>',
         '<p>The back-office, which holds budgets, expenses and client history, is the most protected point in the system: <strong>several independent layers of protection</strong>, mandatory two-factor authentication and access reserved to a few internal roles, some limited to a single module.</p><p></p><p>Each user only receives the rights their role requires, and sensitive actions are logged. Even the most powerful account does not hold its full powers by default: they are activated explicitly, for a limited time. That is least privilege, applied all the way to the top of the hierarchy.</p>'],
        ['<p>The conversational assistant runs on a hybrid architecture deliberately isolated from the rest of the application: content ingestion (summary and embedding) runs as a background task on every publication, and real-time conversation relies on a similarity search over that same data.</p>',
         '<p>The conversational assistant is deliberately isolated from the rest of the application. It answers from the portfolio\'s published content: profile, projects, articles, offers. It is protected against hijacking attempts, and its answers are filtered before display.</p>'],
    ];

    /** Lignes de pile technique : préfixe → [ligne FR, ligne EN]. Remplacées seulement si elles diffèrent. */
    public const TECH_LINES = [
        'Backend' => ['Backend — PHP, Symfony, API Platform, Doctrine, MySQL', 'Backend — PHP, Symfony, API Platform, Doctrine, MySQL'],
        'Frontend' => ['Frontend — Next.js, React, TypeScript, Tailwind, next-intl', 'Frontend — Next.js, React, TypeScript, Tailwind, next-intl'],
        'Infrastructure' => ['Infrastructure — Docker, intégration et déploiement continus', 'Infrastructure — Docker, continuous integration and delivery'],
        'Observabilité' => ['Observabilité — supervision et journalisation des événements de sécurité', 'Observability — monitoring and security event logging'],
        'Observability' => ['Observabilité — supervision et journalisation des événements de sécurité', 'Observability — monitoring and security event logging'],
    ];

    /** Défi n° 4 (index 3) : solution nettoyée [FR, EN], appliquée si l'actuelle cite le champ anti-spam. */
    public const CHALLENGE_4 = [
        'Formulaire court, protections anti-robots, confirmation par email et revue manuelle avant activation : le vivier reste exploitable au lieu de se remplir de candidatures inutilisables.',
        'Short form, anti-bot protections, email confirmation and manual review before activation: the talent pool stays usable instead of filling up with unusable applications.',
    ];

    /** Identifiants hérités du prototype → identifiants réels. */
    public const OFFER_SLUGS = [
        'application' => 'logiciel-sur-mesure',
        'architecture' => 'architecture-logicielle',
        'site' => 'site-professionnel',
        'audit' => 'audit-securite',
        'risques' => 'gestion-des-risques',
        'whatsapp' => 'whatsapp-ai',
        'automatisation' => 'automatisation-process-metiertaches',
    ];

    /** Réalisations « preuve » héritées du prototype → réalisation réelle (null = aucune). */
    public const PROOF_SLUGS = [
        'dbg' => self::PROJECT_SLUG,
        'agent' => null,
    ];

    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly SkillDomainRepository $domains,
        private readonly OfferRepository $offers,
        private readonly EntityManagerInterface $em,
        private readonly TranslationRepository $translations,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Écrit réellement les changements (sinon : aperçu seulement).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $changes = [];
        $warnings = [];

        // --- 1. Fiche Portail Digital ---
        $project = $this->projects->findOneBy(['slug' => self::PROJECT_SLUG]);
        if (!$project instanceof Project) {
            $warnings[] = 'Fiche « '.self::PROJECT_SLUG.' » introuvable : rien à nettoyer.';
        } else {
            foreach (['FR' => [self::DESCRIPTION_FR, false], 'EN' => [self::DESCRIPTION_EN, true]] as $lang => [$pairs, $en]) {
                // Comparaison insensible aux variantes d'encodage HTML : le nettoyeur
                // de l'éditeur riche réécrit « &#39; » en « &#039; », « + » en « &#43; »…
                $text = self::normalize((string) ($en ? $project->getDescriptionEn() : $project->getDescription()));
                foreach ($pairs as $i => [$old, $new]) {
                    $old = self::normalize($old);
                    $new = self::normalize($new);
                    if (str_contains($text, $old)) {
                        $text = str_replace($old, $new, $text);
                        $changes[] = ['Description '.$lang, 'passage '.($i + 1), mb_strimwidth(strip_tags($new), 0, 70, '…')];
                    } elseif (!str_contains($text, $new)) {
                        $warnings[] = "Description {$lang}, passage ".($i + 1).' : déjà modifié à la main, laissé tel quel — à vérifier.';
                    }
                }
                $en ? $project->setDescriptionEn($text) : $project->setDescription($text);
            }

            $info = $project->getInfo();
            if (null !== $info) {
                foreach ([false, true] as $en) {
                    $stack = ($en ? $info->getTechStackEn() : $info->getTechStack()) ?? [];
                    foreach ($stack as $k => $row) {
                        $prefix = trim((string) strtok((string) ($row['name'] ?? ''), '—'));
                        $target = self::TECH_LINES[$prefix][$en ? 1 : 0] ?? null;
                        if (null !== $target && ($row['name'] ?? '') !== $target) {
                            $stack[$k]['name'] = $target;
                            $changes[] = ['Pile technique '.($en ? 'EN' : 'FR'), $prefix, $target];
                        }
                    }
                    $en ? $info->setTechStackEn($stack) : $info->setTechStack($stack);

                    $challenges = ($en ? $info->getChallengesEn() : $info->getChallenges()) ?? [];
                    $solution = (string) ($challenges[3]['solution'] ?? '');
                    if (1 === preg_match('/leurre|honeypot/iu', $solution)) {
                        $challenges[3]['solution'] = self::CHALLENGE_4[$en ? 1 : 0];
                        $changes[] = ['Défi n° 4 '.($en ? 'EN' : 'FR'), 'solution', mb_strimwidth(self::CHALLENGE_4[$en ? 1 : 0], 0, 70, '…')];
                        $en ? $info->setChallengesEn($challenges) : $info->setChallenges($challenges);
                    }
                }
            }
        }

        // --- 2. Domaines de compétence ---
        $published = [];
        foreach ($this->offers->findBy(['published' => true]) as $offer) {
            $published[$offer->getSlug()] = true;
        }
        foreach ($this->domains->findOrdered() as $domain) {
            $slugs = [];
            foreach ($domain->getOfferSlugs() as $slug) {
                $slugs[] = self::OFFER_SLUGS[$slug] ?? $slug;
            }
            // Seules les offres réellement publiées sont gardées (jamais un slug fantôme).
            $slugs = array_values(array_unique(array_filter($slugs, static fn (string $s): bool => isset($published[$s]))));
            if ($slugs !== $domain->getOfferSlugs()) {
                $changes[] = ['Domaine', $domain->getTitle(), 'offres : '.implode(', ', $slugs)];
                $domain->setOfferSlugs($slugs);
            }
            $proof = $domain->getProofProjectSlug();
            if (null !== $proof && \array_key_exists($proof, self::PROOF_SLUGS)) {
                $domain->setProofProjectSlug(self::PROOF_SLUGS[$proof]);
                $changes[] = ['Domaine', $domain->getTitle(), 'réalisation : '.(self::PROOF_SLUGS[$proof] ?? 'aucune')];
            }
        }

        foreach ($warnings as $w) {
            $io->warning($w);
        }
        if ([] === $changes) {
            $this->em->clear();
            $io->success('Rien à changer : le contenu est déjà propre.');

            return Command::SUCCESS;
        }
        $io->table(['Élément', 'Partie', 'Nouveau'], $changes);

        if (!$force) {
            // Aperçu : les entités modifiées en mémoire ne sont jamais écrites.
            $this->em->clear();
            $io->note(\sprintf('%d changement(s) seraient appliqués. Relancez avec --force pour écrire.', \count($changes)));

            return Command::SUCCESS;
        }

        $this->em->flush();
        if ($project instanceof Project) {
            // Description EN : table `translation`, écrite après flush.
            $this->translations->syncFromEntity($project);
        }
        $io->success(\sprintf('%d changement(s) appliqués. Pensez à relancer app:assistant:reingest --all.', \count($changes)));

        return Command::SUCCESS;
    }

    /** Ramène les entités HTML équivalentes à une seule forme (apostrophes, guillemets, plus). */
    private static function normalize(string $html): string
    {
        return str_replace(['&#039;', '&#39;', '&apos;', '&#43;', '&#34;', '&quot;'], ["'", "'", "'", '+', '"', '"'], $html);
    }
}
