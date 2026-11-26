<?php

namespace App\Tests\Functional;

use App\Command\AboutApplyPrototypeCommand;
use App\Entity\SkillDomain;
use App\Repository\AboutContentRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Page À propos fidèle au prototype (2026-11-26) :
 *  - domaines de compétence éditables au back-office (ROLE_ADMIN, CSRF),
 *    exposés en lecture seule par l'API publique ;
 *  - commande app:about:apply-prototype : aperçu sans écriture, puis --force
 *    écrit les textes du prototype en français ET en anglais, idempotente.
 *
 * Tables partagées : la ligne À propos et ses traductions sont sauvegardées
 * puis restaurées ; les domaines créés ici sont supprimés au tearDown.
 */
final class SkillDomainAndPrototypeTest extends FunctionalTestCase
{
    /** @var array<string, mixed> */
    private array $rowBackup = [];
    /** @var list<array<string, mixed>> */
    private array $translationBackup = [];
    private int $aboutId;
    /** @var list<int> */
    private array $domainsBefore = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->aboutId = (int) static::getContainer()->get(AboutContentRepository::class)->getContent()->getId();
        $conn = $this->em()->getConnection();
        $this->rowBackup = $conn->fetchAssociative('SELECT * FROM about_content WHERE id = ?', [$this->aboutId]) ?: [];
        $this->translationBackup = $conn->fetchAllAssociative("SELECT entity_type, entity_id, field, locale, value FROM translation WHERE entity_type = 'AboutContent' AND entity_id = ?", [$this->aboutId]);
        $this->domainsBefore = array_map('intval', $conn->fetchFirstColumn('SELECT id FROM skill_domain'));
    }

    protected function tearDown(): void
    {
        $conn = $this->em()->getConnection();
        try {
            $row = $this->rowBackup;
            unset($row['id']);
            if ([] !== $row) {
                $conn->update('about_content', $row, ['id' => $this->aboutId]);
            }
            $conn->executeStatement("DELETE FROM translation WHERE entity_type = 'AboutContent' AND entity_id = ?", [$this->aboutId]);
            foreach ($this->translationBackup as $t) {
                $conn->insert('translation', $t);
            }
            if ([] !== $this->domainsBefore) {
                $conn->executeStatement('DELETE FROM skill_domain WHERE id NOT IN ('.implode(',', $this->domainsBefore).')');
            } else {
                $conn->executeStatement('DELETE FROM skill_domain');
            }
        } finally {
            parent::tearDown();
        }
    }

    private function loginAs(string $role): void
    {
        $this->client->loginUser($this->createUser([$role], twoFactor: true), 'main');
    }

    public function testAdminCreatesADomainWithListsAndLinks(): void
    {
        $this->loginAs('ROLE_ADMIN');
        $crawler = $this->client->request('GET', '/admin/content/skill-domains/create');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="skill_domain"]')->form();

        $this->client->submit($form, [
            'skill_domain[position]' => '7',
            'skill_domain[icon]' => 'lock',
            'skill_domain[title]' => 'Domaine créé en test',
            'skill_domain[titleEn]' => 'Test area',
            'skill_domain[text]' => 'Présentation.',
            'skill_domain[doItems]' => "Auditer\n\n  Durcir  ",
            'skill_domain[tools]' => "OWASP\nRBAC",
        ]);
        self::assertResponseRedirects('/admin/content/skill-domains/');

        $this->em()->clear();
        $domain = $this->em()->getRepository(SkillDomain::class)->findOneBy(['title' => 'Domaine créé en test']);
        self::assertNotNull($domain);
        self::assertSame(['Auditer', 'Durcir'], $domain->getDoItems());
        self::assertSame(['OWASP', 'RBAC'], $domain->getTools());
        self::assertNull($domain->getToolsEn(), 'Liste anglaise vide = repli sur le français.');
        self::assertSame('Test area', $domain->getTitleEn());
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function invalidDomains(): iterable
    {
        yield 'titre vide' => [['skill_domain[title]' => '   ']];
        yield 'icône inventée' => [['skill_domain[icon]' => 'evil']];
        yield 'contrôle bidi dans un outil' => [['skill_domain[tools]' => "OWASP\na\u{202E}b"]];
        yield 'texte trop long' => [['skill_domain[text]' => str_repeat('a', SkillDomain::TEXT_MAX + 1)]];
        yield 'offre inexistante' => [['skill_domain[offerSlugs][]' => 'n-existe-pas']];
    }

    /** @param array<string, string> $override */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidDomains')]
    public function testInvalidDomainIsAFormErrorNeverA500(array $override): void
    {
        $this->loginAs('ROLE_ADMIN');
        $before = (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM skill_domain');
        $this->client->request('POST', '/admin/content/skill-domains/create', ['skill_domain' => array_merge(
            ['position' => '1', 'icon' => 'arch', 'title' => 'OK', 'text' => '', 'doItems' => '', 'tools' => '', '_token' => $this->csrf('skill_domain')],
            $this->unwrap($override),
        )]);

        self::assertContains($this->client->getResponse()->getStatusCode(), [200, 422], 'Erreur de formulaire, jamais 500.');
        self::assertSame($before, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM skill_domain'));
    }

    /** Témoin : la même soumission, valide, enregistre — les refus ci-dessus ne viennent donc pas du jeton. */
    public function testValidRawSubmissionIsSaved(): void
    {
        $this->loginAs('ROLE_ADMIN');
        $before = (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM skill_domain');
        $this->client->request('POST', '/admin/content/skill-domains/create', ['skill_domain' => [
            'position' => '1', 'icon' => 'arch', 'title' => 'OK', 'text' => '', 'doItems' => '', 'tools' => '', '_token' => $this->csrf('skill_domain'),
        ]]);

        self::assertResponseRedirects('/admin/content/skill-domains/');
        self::assertSame($before + 1, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM skill_domain'));
    }

    public function testDomainsScreenIsAdminOnlyAndDeleteNeedsCsrf(): void
    {
        $this->loginAs('ROLE_EDITOR');
        $this->client->request('GET', '/admin/content/skill-domains/');
        self::assertResponseStatusCodeSame(403);

        $this->loginAs('ROLE_ADMIN');
        $domain = (new SkillDomain())->setTitle('À supprimer')->setPosition(9);
        $this->em()->persist($domain);
        $this->em()->flush();
        $id = (int) $domain->getId();

        $this->client->request('POST', "/admin/content/skill-domains/{$id}/delete", ['_token' => 'faux']);
        self::assertSame(1, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM skill_domain WHERE id = ?', [$id]), 'Jeton invalide : rien supprimé.');
    }

    public function testPublicApiListsDomainsInOrderAndRefusesWrites(): void
    {
        $this->em()->persist((new SkillDomain())->setTitle('Zz dernier')->setPosition(998));
        $this->em()->persist((new SkillDomain())->setTitle('Aa premier')->setPosition(-5));
        $this->em()->flush();

        $this->client->request('GET', '/api/skill_domains', server: ['HTTP_ACCEPT' => 'application/ld+json']);
        self::assertResponseIsSuccessful();
        $titles = array_column(json_decode((string) $this->client->getResponse()->getContent(), true)['member'] ?? [], 'title');
        self::assertSame('Aa premier', $titles[0]);
        self::assertSame('Zz dernier', end($titles));

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->client->request($method, '/api/skill_domains'.('POST' === $method ? '' : '/1'), server: ['CONTENT_TYPE' => 'application/ld+json'], content: '{"title":"pirate"}');
            self::assertContains($this->client->getResponse()->getStatusCode(), [404, 405], $method.' refusé : lecture seule.');
        }
    }

    public function testApplyPrototypePreviewsThenWritesFrenchAndEnglishIdempotently(): void
    {
        $repo = static::getContainer()->get(AboutContentRepository::class);
        $content = $repo->getContent();
        $content->setHeroTitle('Ancien titre')->setBioP1('Ancienne bio');
        $this->em()->flush();

        $tester = new CommandTester((new Application(static::$kernel))->find('app:about:apply-prototype'));

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('--force', $tester->getDisplay());
        $this->em()->clear();
        self::assertSame('Ancien titre', $repo->getContent()->getHeroTitle(), 'Aperçu : aucune écriture.');

        $tester->execute(['--force' => true]);
        $tester->assertCommandIsSuccessful();
        $this->em()->clear();
        $about = $repo->getContent();
        self::assertSame('Ingénieur et', $about->getHeroTitle());
        self::assertSame('architecte logiciel.', $about->getHeroTitleAccent());
        self::assertSame(AboutApplyPrototypeCommand::TEXTS['bioP1'][0], $about->getBioP1());
        self::assertSame('Horlynat Mampassi Mbama', $about->getProfileName());
        self::assertSame('Software engineer', $about->getHeroTitleEn(), 'Anglais écrit dans la table translation.');
        self::assertSame(AboutApplyPrototypeCommand::TEXTS['ctaSub'][1], $about->getCtaSubEn());

        $tester->execute(['--force' => true]);
        self::assertStringContainsString('déjà les textes du prototype', $tester->getDisplay());
    }

    /**
     * @param array<string, string> $override
     *
     * @return array<string, mixed>
     */
    private function unwrap(array $override): array
    {
        $out = [];
        foreach ($override as $key => $value) {
            if (1 === preg_match('/^skill_domain\[(\w+)\](\[\])?$/', $key, $m)) {
                $out[$m[1]] = '' !== ($m[2] ?? '') ? [$value] : $value;
            }
        }

        return $out;
    }

    private function csrf(string $id): string
    {
        $crawler = $this->client->request('GET', '/admin/content/skill-domains/create');

        return (string) $crawler->filter('input[name="'.$id.'[_token]"]')->attr('value');
    }
}
