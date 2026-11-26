<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Page À propos fidèle au prototype, entièrement pilotée par le back-office :
 *  - retire les champs jamais affichés (accroche, « Aussi », localisation,
 *    mode de travail, langues, titre de vision, « Au-delà du code », bio 3)
 *    et leurs traductions anglaises (table `translation`) ;
 *  - ajoute les titres et chapôs des sections Compétences, Réalisations,
 *    Parcours, pré-remplis avec les textes affichés jusqu'ici (FR + EN) ;
 *  - crée `skill_domain` (onglets Compétences), pré-rempli avec les 4
 *    domaines affichés jusqu'ici — sans « Cloudflare » (zéro information
 *    de configuration de sécurité, décision du 2026-11-26).
 * Aucune donnée affichée n'est perdue ; seules les colonnes jamais rendues
 * sur le site disparaissent (validé par le propriétaire).
 */
final class Version20261126120000 extends AbstractMigration
{
    private const REMOVED_FIELDS = ['heroEyebrow', 'profileAlso', 'profileLocation', 'profileWorkMode', 'profileLanguages', 'visionTitle', 'beyondLanguages', 'beyondInterests', 'bioP3'];

    /** Champ => [texte FR, texte EN] (textes affichés jusqu'ici par le site). */
    private const SECTIONS = [
        'skillsTitle' => ['Quatre domaines, un même niveau d’exigence', 'Four fields, one standard of excellence'],
        'skillsLede' => ['Choisissez un domaine : ce que je fais pour vous, mes outils du moment, les offres et la preuve associées.', 'Pick a field: what I do for you, my current tools, and the related services and proof.'],
        'skillsNote' => ['Liste indicative : j’apprends en continu les technologies que chaque projet exige.', 'Indicative list: I keep learning the technologies each project requires.'],
        'projectsTitle' => ['Des plateformes conçues, livrées et exploitées', 'Platforms designed, delivered and run'],
        'projectsLede' => ['Chaque réalisation est en service ou en cours de déploiement. Ouvrez une étude de cas pour en comprendre la démarche.', 'Every project is live or being rolled out. Open a case study to understand the approach.'],
        'pathTitle' => ['Des mathématiques à l\'entreprise', 'From mathematics to business'],
        'pathLede' => ['Informatique, assurance puis entrepreneuriat : un parcours qui m\'a appris à construire et à anticiper les risques.', 'Computing, insurance, then entrepreneurship: a path that taught me to build and to anticipate risks.'],
    ];

    private const COLUMNS = ['skills_title', 'skills_lede', 'skills_note', 'projects_title', 'projects_lede', 'path_title', 'path_lede'];

    private const DOMAINS = [
        [
            'position' => 1, 'icon' => 'arch',
            'title' => 'Ingénierie et architecture logicielle', 'title_en' => 'Software engineering and architecture',
            'short' => 'Concevoir des logiciels solides, qui grandissent avec l’activité.', 'short_en' => 'Building solid software that grows with the business.',
            'text' => 'Je conçois l’architecture, je développe et je mets en service des plateformes web, des applications mobiles et des outils internes. Chaque choix technique est fait pour durer et pour évoluer sans refonte coûteuse.', 'text_en' => 'I design the architecture, develop and launch web platforms, mobile apps and internal tools. Every technical choice is made to last and to evolve without a costly rewrite.',
            'do_items' => ['Concevoir l’architecture d’une plateforme', 'Développer des applications web et mobiles', 'Concevoir des API et modéliser les données', 'Auditer et faire évoluer un logiciel existant'], 'do_items_en' => ['Design a platform’s architecture', 'Build web and mobile applications', 'Design APIs and data models', 'Audit and evolve existing software'],
            'tools' => ['PHP', 'Python', 'JavaScript', 'TypeScript', 'SQL', 'Dart', 'Symfony', 'API Platform', 'Next.js', 'React', 'Flutter', 'Docker', 'Kubernetes', 'CI/CD', 'Linux'], 'tools_en' => null,
            'offer_slugs' => ['application', 'architecture', 'site'], 'proof_project_slug' => 'dbg',
        ],
        [
            'position' => 2, 'icon' => 'lock',
            'title' => 'Cybersécurité', 'title_en' => 'Cybersecurity',
            'short' => 'La sécurité posée dès la conception, puis vérifiée.', 'short_en' => 'Security built in from the design stage, then verified.',
            'text' => 'Je réfléchis à la surface d’attaque de chaque fonctionnalité avant de l’écrire, puis je vérifie : audits, tests d’intrusion, durcissement et surveillance.', 'text_en' => 'I think about the attack surface of each feature before writing it, then I verify: audits, penetration tests, hardening and monitoring.',
            'do_items' => ['Audits et tests d’intrusion', 'Durcissement des serveurs et des applications', 'Gestion des accès et authentification', 'Conformité : RGPD, ISO 27001, loi congolaise n° 29-2019'], 'do_items_en' => ['Audits and penetration tests', 'Server and application hardening', 'Access management and authentication', 'Compliance: GDPR, ISO 27001, Congolese law no. 29-2019'],
            'tools' => ['OWASP', 'Tests d’intrusion', 'RBAC', 'OAuth', 'JWT', 'Double authentification'], 'tools_en' => ['OWASP', 'Penetration testing', 'RBAC', 'OAuth', 'JWT', 'Two-factor authentication'],
            'offer_slugs' => ['audit'], 'proof_project_slug' => 'dbg',
        ],
        [
            'position' => 3, 'icon' => 'umb',
            'title' => 'Gestion et management des risques', 'title_en' => 'Risk management',
            'short' => 'Depuis 2018, appliquée à chaque projet.', 'short_en' => 'Since 2018, applied to every project.',
            'text' => 'Responsable des risques divers, transports, corps et facultés pendant quatre ans, j’ai appris à repérer ce qui peut mal tourner et à vérifier ce qu’un contrat couvre vraiment.', 'text_en' => 'As head of miscellaneous, transport, hull and cargo risks for four years, I learned to spot what can go wrong and to check what a contract really covers.',
            'do_items' => ['Analyse et cartographie des risques', 'Audit des couvertures d’assurance', 'Risques divers, transport, corps et facultés', 'Prévention et continuité d’activité'], 'do_items_en' => ['Risk analysis and mapping', 'Insurance coverage audits', 'Miscellaneous, transport, hull and cargo risks', 'Prevention and business continuity'],
            'tools' => ['Assurance non-vie', 'Souscription', 'Gestion de sinistres', 'Continuité d’activité'], 'tools_en' => ['Non-life insurance', 'Underwriting', 'Claims management', 'Business continuity'],
            'offer_slugs' => ['risques'], 'proof_project_slug' => null,
        ],
        [
            'position' => 4, 'icon' => 'spark',
            'title' => 'IA et automatisation', 'title_en' => 'AI and automation',
            'short' => 'Des assistants et des automatisations utiles au quotidien.', 'short_en' => 'Assistants and automations that help every day.',
            'text' => 'J’intègre l’intelligence artificielle là où elle fait gagner du temps ou des ventes : assistants sur WhatsApp et sur les sites, exploitation des documents, automatisation des tâches répétitives.', 'text_en' => 'I bring in artificial intelligence where it saves time or wins sales: assistants on WhatsApp and websites, document processing, automation of repetitive tasks.',
            'do_items' => ['Assistants IA sur WhatsApp et sur site', 'Réponses fondées sur vos propres documents', 'Automatisation des tâches répétitives', 'Intégration à vos outils existants'], 'do_items_en' => ['AI assistants on WhatsApp and websites', 'Answers based on your own documents', 'Automation of repetitive tasks', 'Integration with your existing tools'],
            'tools' => ['Claude', 'Gemini', 'RAG', 'Python', 'PyTorch', 'TensorFlow', 'API WhatsApp'], 'tools_en' => ['Claude', 'Gemini', 'RAG', 'Python', 'PyTorch', 'TensorFlow', 'WhatsApp API'],
            'offer_slugs' => ['whatsapp', 'automatisation'], 'proof_project_slug' => 'agent',
        ],
    ];

    public function getDescription(): string
    {
        return 'À propos = prototype : champs inutiles retirés, titres de sections et domaines de compétence pilotés par le back-office.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE about_content DROP hero_eyebrow, DROP profile_also, DROP profile_location, DROP profile_work_mode, DROP profile_languages, DROP vision_title, DROP beyond_languages, DROP beyond_interests, DROP bio_p3');
        $this->addSql("ALTER TABLE about_content ADD skills_title VARCHAR(255) NOT NULL DEFAULT '', ADD skills_lede LONGTEXT NOT NULL, ADD skills_note LONGTEXT NOT NULL, ADD projects_title VARCHAR(255) NOT NULL DEFAULT '', ADD projects_lede LONGTEXT NOT NULL, ADD path_title VARCHAR(255) NOT NULL DEFAULT '', ADD path_lede LONGTEXT NOT NULL");
        $this->addSql('ALTER TABLE about_content ALTER skills_title DROP DEFAULT, ALTER projects_title DROP DEFAULT, ALTER path_title DROP DEFAULT');

        $this->addSql(
            "DELETE FROM translation WHERE entity_type = 'AboutContent' AND field IN (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            self::REMOVED_FIELDS,
        );

        $i = 0;
        foreach (self::SECTIONS as $field => [$fr, $en]) {
            $column = self::COLUMNS[$i++];
            $this->addSql("UPDATE about_content SET {$column} = ?", [$fr]);
            $this->addSql(
                "INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, ?, 'en', ? FROM about_content",
                [$field, $en],
            );
        }

        $this->addSql('CREATE TABLE skill_domain (id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, icon VARCHAR(20) NOT NULL, title VARCHAR(160) NOT NULL, title_en VARCHAR(160) DEFAULT NULL, short VARCHAR(160) NOT NULL, short_en VARCHAR(160) DEFAULT NULL, text LONGTEXT NOT NULL, text_en LONGTEXT DEFAULT NULL, do_items JSON NOT NULL, do_items_en JSON DEFAULT NULL, tools JSON NOT NULL, tools_en JSON DEFAULT NULL, offer_slugs JSON NOT NULL, proof_project_slug VARCHAR(120) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        foreach (self::DOMAINS as $d) {
            $this->addSql(
                'INSERT INTO skill_domain (position, icon, title, title_en, short, short_en, text, text_en, do_items, do_items_en, tools, tools_en, offer_slugs, proof_project_slug) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $d['position'], $d['icon'], $d['title'], $d['title_en'], $d['short'], $d['short_en'], $d['text'], $d['text_en'],
                    json_encode($d['do_items'], \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR), json_encode($d['do_items_en'], \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR),
                    json_encode($d['tools'], \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR), null === $d['tools_en'] ? null : json_encode($d['tools_en'], \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR),
                    json_encode($d['offer_slugs'], \JSON_THROW_ON_ERROR), $d['proof_project_slug'],
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE skill_domain');
        $this->addSql("DELETE FROM translation WHERE entity_type = 'AboutContent' AND field IN ('skillsTitle', 'skillsLede', 'skillsNote', 'projectsTitle', 'projectsLede', 'pathTitle', 'pathLede')");
        $this->addSql('ALTER TABLE about_content DROP skills_title, DROP skills_lede, DROP skills_note, DROP projects_title, DROP projects_lede, DROP path_title, DROP path_lede');
        // Colonnes recréées vides : leur contenu (jamais affiché) n'est pas restauré.
        $this->addSql("ALTER TABLE about_content ADD hero_eyebrow VARCHAR(255) NOT NULL DEFAULT '', ADD profile_also VARCHAR(255) NOT NULL DEFAULT '', ADD profile_location VARCHAR(255) NOT NULL DEFAULT '', ADD profile_work_mode VARCHAR(255) NOT NULL DEFAULT '', ADD profile_languages VARCHAR(255) NOT NULL DEFAULT '', ADD vision_title VARCHAR(255) NOT NULL DEFAULT '', ADD beyond_languages JSON NOT NULL, ADD beyond_interests JSON NOT NULL, ADD bio_p3 LONGTEXT NOT NULL");
        $this->addSql("UPDATE about_content SET beyond_languages = '[]', beyond_interests = '[]'");
    }
}
