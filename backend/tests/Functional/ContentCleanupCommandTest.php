<?php

namespace App\Tests\Functional;

use App\Command\ContentCleanupCommand;
use App\Entity\Offer;
use App\Entity\Project;
use App\Entity\ProjectInfo;
use App\Entity\SkillDomain;
use App\Enum\ProjectStatusEnum;
use App\Repository\TranslationRepository;
use App\Service\AiAssistantOutputSanitizer;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * app:content:cleanup sur une copie fidèle de la fiche de production
 * (passages exacts, lignes de pile, défi n° 4) : aperçu sans écriture,
 * --force qui retire toute configuration, idempotence, domaines reliés aux
 * vraies offres publiées seulement.
 */
final class ContentCleanupCommandTest extends FunctionalTestCase
{
    /** @var list<array{string, int}> */
    private array $created = [];

    protected function tearDown(): void
    {
        $conn = $this->em()->getConnection();
        try {
            foreach (array_reverse($this->created) as [$table, $id]) {
                if ('project' === $table) {
                    $conn->executeStatement("DELETE FROM translation WHERE entity_type = 'Project' AND entity_id = ?", [$id]);
                    $conn->executeStatement('DELETE FROM project_info WHERE project_id = ?', [$id]);
                    $conn->executeStatement('DELETE FROM project WHERE id = ?', [$id]);
                    continue;
                }
                $conn->executeStatement("DELETE FROM `{$table}` WHERE id = ?", [$id]);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function productionLikeProject(): Project
    {
        $owner = $this->createUser(['ROLE_ADMIN']);
        // Les passages exacts de production, entourés de contenu qui doit rester intact.
        $fr = '<h2>Le projet</h2><p>Introduction conservée.</p>'.implode('<p>Entre-deux conservé.</p>', array_column(ContentCleanupCommand::DESCRIPTION_FR, 0));
        $en = '<h2>The project</h2><p>Kept intro.</p>'.implode('<p>Kept in-between.</p>', array_column(ContentCleanupCommand::DESCRIPTION_EN, 0));
        $stackFr = [
            ['name' => 'Backend — PHP 8.4, Symfony 8.1, API Platform 4, Doctrine ORM, MySQL 8, JWT (lexik/jwt-bundle), 2FA TOTP (scheb/2fa-bundle)', 'rationale' => null],
            ['name' => 'IA — Gemini (ingestion RAG, embeddings), Claude (conversation temps réel)', 'rationale' => null],
            ['name' => 'Infrastructure — Docker Compose, Traefik, Cloudflare (WAF, Access Zero Trust, TLS Full Strict), GitHub Actions, VPS Ubuntu 24', 'rationale' => null],
            ['name' => 'Observabilité — Netdata, UptimeRobot / Better Stack, journalisation Monolog dédiée à la sécurité', 'rationale' => null],
        ];
        $stackEn = [
            ['name' => 'Backend — PHP 8.4, Symfony 8.1, API Platform 4, Doctrine ORM, MySQL 8, JWT (lexik/jwt-bundle), 2FA TOTP (scheb/2fa-bundle)', 'rationale' => null],
            ['name' => 'Observability — Netdata, UptimeRobot / Better Stack, dedicated security Monolog logging', 'rationale' => null],
        ];
        $challenge = static fn (string $s): array => [['problem' => 'a', 'solution' => 'b'], ['problem' => 'c', 'solution' => 'd'], ['problem' => 'e', 'solution' => 'f'], ['problem' => 'Spam', 'solution' => $s]];
        $info = (new ProjectInfo())->setTechStack($stackFr)->setTechStackEn($stackEn)
            ->setChallenges($challenge('Formulaire court, champ leurre, confirmation par email.'))
            ->setChallengesEn($challenge('Short form, honeypot field, email confirmation.'));
        $project = (new Project())->setTitle('Horlynat | Portail Digital')->setDescription($fr)->setLink('')->setOwner($owner)
            ->setStatus(ProjectStatusEnum::COMPLETED)->setInfo($info);
        $project->setSlug(ContentCleanupCommand::PROJECT_SLUG);
        $project->setDescriptionEn($en);
        $this->em()->persist($info);
        $this->em()->persist($project);
        $this->em()->flush();
        static::getContainer()->get(TranslationRepository::class)->syncFromEntity($project);
        $this->created[] = ['project', (int) $project->getId()];

        return $project;
    }

    private function offer(string $slug, bool $published): void
    {
        $o = (new Offer())->setSlug($slug)->setPublished($published)->setPosition(990)->setTitle("Offre {$slug}")
            ->setShortTitle('c')->setPain('p')->setHeadline('h')->setLeadText('l')->setCardText('c')->setAudience('a')->setClientNeeds('n')
            ->setDelay('1 jour')->setPrice(1000)->setPriceOnce(false)->setIncludes(['x']);
        $this->em()->persist($o);
        $this->em()->flush();
        $this->created[] = ['offer', (int) $o->getId()];
    }

    public function testCleansTheProductionRecordAndLinksDomainsToRealOffers(): void
    {
        $conn = $this->em()->getConnection();
        if ($conn->fetchOne('SELECT COUNT(*) FROM project WHERE slug = ?', [ContentCleanupCommand::PROJECT_SLUG]) > 0) {
            self::markTestSkipped('Une fiche Portail Digital existe déjà dans cette base de test.');
        }
        $project = $this->productionLikeProject();
        $id = (int) $project->getId();
        $this->offer('logiciel-sur-mesure', true);
        $this->offer('architecture-logicielle', false); // non publiée : jamais reliée
        $domain = (new SkillDomain())->setTitle('Domaine test')->setPosition(1)->setOfferSlugs(['application', 'architecture', 'inconnue'])->setProofProjectSlug('dbg');
        $this->em()->persist($domain);
        $this->em()->flush();
        $this->created[] = ['skill_domain', (int) $domain->getId()];

        $tester = new CommandTester((new Application(static::$kernel))->find('app:content:cleanup'));

        // Aperçu : rien n'est écrit.
        $before = $conn->fetchOne('SELECT description FROM project WHERE id = ?', [$id]);
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('--force', $tester->getDisplay());
        self::assertSame($before, $conn->fetchOne('SELECT description FROM project WHERE id = ?', [$id]));
        self::assertSame('["application", "architecture", "inconnue"]', $conn->fetchOne('SELECT offer_slugs FROM skill_domain WHERE id = ?', [$domain->getId()]));

        // --force : plus aucune configuration, contenu non visé intact.
        $tester->execute(['--force' => true]);
        $tester->assertCommandIsSuccessful();
        $this->em()->clear();
        $p = $this->em()->find(Project::class, $id);
        self::assertNotNull($p);
        $info = $p->getInfo();
        self::assertNotNull($info);
        $sanitizer = new AiAssistantOutputSanitizer();
        $everything = implode("\n", [
            $p->getDescription(), (string) $p->getDescriptionEn(),
            json_encode($info->getTechStack(), \JSON_UNESCAPED_UNICODE), json_encode($info->getTechStackEn(), \JSON_UNESCAPED_UNICODE),
            json_encode($info->getChallenges(), \JSON_UNESCAPED_UNICODE), json_encode($info->getChallengesEn(), \JSON_UNESCAPED_UNICODE),
        ]);
        foreach (['Traefik', 'Cloudflare', 'ROLE_', 'lexik', 'scheb', 'Netdata', 'UptimeRobot', 'Monolog', 'Ubuntu', 'leurre', 'honeypot', 'ProjectExpense', 'TimeEntry', 'Zero Trust', 'GitHub Actions', 'VPS', 'TOTP', '8.4', 'MySQL 8'] as $term) {
            self::assertStringNotContainsStringIgnoringCase($term, $everything, $term);
        }
        self::assertFalse($sanitizer->revealsSecurityConfig($everything), 'Le verrou de l\'assistant ne trouve plus rien.');
        self::assertStringContainsString('Introduction conservée.', $p->getDescription());
        self::assertStringContainsString('Entre-deux conservé.', $p->getDescription());
        self::assertStringContainsString('Kept in-between.', (string) $p->getDescriptionEn(), 'Description EN (table translation) écrite.');
        self::assertSame('IA — Gemini (ingestion RAG, embeddings), Claude (conversation temps réel)', $info->getTechStack()[1]['name'], 'Ligne non visée intacte.');
        self::assertStringContainsString('protections anti-robots', $info->getChallenges()[3]['solution']);
        self::assertSame('b', $info->getChallenges()[0]['solution'], 'Autres défis intacts.');
        $d = $this->em()->find(SkillDomain::class, $domain->getId());
        self::assertNotNull($d);
        self::assertSame(['logiciel-sur-mesure'], $d->getOfferSlugs(), 'Seules les offres publiées existantes.');
        self::assertSame(ContentCleanupCommand::PROJECT_SLUG, $d->getProofProjectSlug());

        // Idempotente.
        $tester->execute(['--force' => true]);
        self::assertStringContainsString('Rien à changer', $tester->getDisplay());
    }

    public function testAPassageAlreadyEditedByHandIsLeftAloneAndReported(): void
    {
        $conn = $this->em()->getConnection();
        if ($conn->fetchOne('SELECT COUNT(*) FROM project WHERE slug = ?', [ContentCleanupCommand::PROJECT_SLUG]) > 0) {
            self::markTestSkipped('Une fiche Portail Digital existe déjà dans cette base de test.');
        }
        $project = $this->productionLikeProject();
        $conn->executeStatement('UPDATE project SET description = ? WHERE id = ?', ['<p>Texte réécrit à la main.</p>', $project->getId()]);
        $this->em()->clear();

        $tester = new CommandTester((new Application(static::$kernel))->find('app:content:cleanup'));
        $tester->execute(['--force' => true]);

        self::assertSame('<p>Texte réécrit à la main.</p>', $conn->fetchOne('SELECT description FROM project WHERE id = ?', [$project->getId()]));
        self::assertStringContainsString('déjà modifié à la main', $tester->getDisplay());
    }
}
