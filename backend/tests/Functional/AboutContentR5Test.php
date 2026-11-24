<?php

namespace App\Tests\Functional;

use App\Entity\AboutContent;
use App\Repository\AboutContentRepository;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Page « À propos », refonte R5 : nouveaux textes dans le back-office
 * (Vision / Mission / Ambition, ambition « D'ici trois ans », repères du
 * parcours, encart « Toujours en formation »), en français et en anglais.
 *
 *  - édition réservée à ROLE_ADMIN (+ 2FA, CSRF), comme le reste de la page ;
 *  - aucune saisie ne doit produire de 500 (leçons B3 / R1) ;
 *  - exposés par l'API publique, traductions comprises.
 *
 * `about_content` est une table à ligne unique, sans rollback entre tests :
 * la ligne et ses traductions sont sauvegardées avant chaque test et
 * restaurées après.
 */
final class AboutContentR5Test extends FunctionalTestCase
{
    private const URL = '/admin/content/about/';
    private const NEW_FIELDS = ['visionPillar', 'missionPillar', 'ambitionPillar', 'horizonMidText', 'learningText', 'journeyStats'];

    /** @var array<string, mixed> */
    private array $rowBackup = [];
    /** @var list<array<string, mixed>> */
    private array $translationBackup = [];
    private int $id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->id = (int) static::getContainer()->get(AboutContentRepository::class)->getContent()->getId();
        $conn = $this->em()->getConnection();
        $this->rowBackup = $conn->fetchAssociative('SELECT * FROM about_content WHERE id = ?', [$this->id]) ?: [];
        $this->translationBackup = $conn->fetchAllAssociative("SELECT entity_type, entity_id, field, locale, value FROM translation WHERE entity_type = 'AboutContent' AND entity_id = ?", [$this->id]);
    }

    protected function tearDown(): void
    {
        $conn = $this->em()->getConnection();
        $row = $this->rowBackup;
        unset($row['id']);
        if ([] !== $row) {
            $conn->update('about_content', $row, ['id' => $this->id]);
        }
        $conn->executeStatement("DELETE FROM translation WHERE entity_type = 'AboutContent' AND entity_id = ?", [$this->id]);
        foreach ($this->translationBackup as $t) {
            $conn->insert('translation', $t);
        }
        parent::tearDown();
    }

    private function loginAdmin(): void
    {
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
    }

    /**
     * Soumet le formulaire réel : les champs français obligatoires encore
     * vides (base de CI neuve) reçoivent une valeur, puis $override.
     *
     * @param array<string, string> $override
     */
    private function submit(array $override): void
    {
        $this->loginAdmin();
        $crawler = $this->client->request('GET', self::URL);
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="about_content"]')->form();

        $values = [];
        foreach ($form->all() as $name => $field) {
            $short = preg_replace('/^about_content\[(.+)\]$/', '$1', $name);
            if (\in_array($short, ['_token', 'profileImageFile'], true) || str_ends_with((string) $short, 'En')) {
                continue;
            }
            if (\in_array($short, self::NEW_FIELDS, true)) {
                continue;
            }
            if ('' === trim((string) $field->getValue())) {
                $values[$name] = 'Valeur de test';
            }
        }
        $this->client->submit($form, array_merge($values, $override));
    }

    private function reloadContent(): AboutContent
    {
        $this->em()->clear();

        return static::getContainer()->get(AboutContentRepository::class)->getContent();
    }

    private function storedVisionPillar(): string
    {
        return (string) $this->em()->getConnection()->fetchOne('SELECT vision_pillar FROM about_content WHERE id = ?', [$this->id]);
    }

    public function testAdminSavesTheNewSectionsInBothLanguages(): void
    {
        $this->submit([
            'about_content[visionPillar]' => 'Une vision de test.',
            'about_content[visionPillarEn]' => 'A test vision.',
            'about_content[missionPillar]' => 'Une mission.',
            // Traduction vidée explicitement : la base peut déjà en contenir
            // une (posée par la migration R5).
            'about_content[missionPillarEn]' => '',
            'about_content[ambitionPillar]' => 'Une ambition.',
            'about_content[horizonMidText]' => 'Dans trois ans.',
            'about_content[learningText]' => "Toujours apprendre.\nAvec un retour à la ligne\tet une tabulation.",
            'about_content[journeyStats]' => "Depuis 2016 | dans le numérique\n2026 | fondation",
            'about_content[journeyStatsEn]' => 'Since 2016 | in digital',
        ]);
        self::assertResponseRedirects(self::URL);

        $c = $this->reloadContent();
        self::assertSame('Une vision de test.', $c->getVisionPillar());
        self::assertSame('A test vision.', $c->getVisionPillarEn());
        self::assertSame("Toujours apprendre.\nAvec un retour à la ligne\tet une tabulation.", $c->getLearningText());
        self::assertEquals([['value' => 'Depuis 2016', 'label' => 'dans le numérique'], ['value' => '2026', 'label' => 'fondation']], $c->getJourneyStats());
        self::assertEquals([['value' => 'Since 2016', 'label' => 'in digital']], $c->getJourneyStatsEn());
        self::assertNull($c->getMissionPillarEn(), 'Une traduction vide reste nulle (repli sur le français).');
    }

    public function testApiExposesTheNewFieldsAndTheirTranslations(): void
    {
        $this->submit([
            'about_content[missionPillar]' => 'Mission publique.',
            'about_content[missionPillarEn]' => 'Public mission.',
            'about_content[journeyStats]' => '2026 | fondation',
        ]);
        self::assertResponseRedirects(self::URL);

        $response = $this->jsonRequest('GET', '/api/about_contents', server: ['HTTP_ACCEPT' => 'application/ld+json']);
        self::assertSame(200, $response->getStatusCode());
        $item = $this->json($response)['member'][0] ?? [];
        self::assertSame('Mission publique.', $item['missionPillar'] ?? null);
        self::assertSame('Public mission.', $item['missionPillarEn'] ?? null);
        self::assertEquals([['value' => '2026', 'label' => 'fondation']], $item['journeyStats'] ?? null);
        foreach (['visionPillar', 'ambitionPillar', 'horizonMidText', 'learningText'] as $key) {
            self::assertArrayHasKey($key, $item, "Champ public « $key » absent de l'API.");
        }
    }

    public function testEmptyOptionalSectionsAreAccepted(): void
    {
        $this->submit(array_fill_keys(array_map(static fn (string $f): string => "about_content[$f]", self::NEW_FIELDS), ''));
        self::assertResponseRedirects(self::URL);

        $c = $this->reloadContent();
        self::assertSame('', $c->getVisionPillar());
        self::assertSame([], $c->getJourneyStats());
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidSubmissions(): iterable
    {
        yield 'repère sans séparateur' => [['about_content[journeyStats]' => 'Depuis 2016 dans le numérique']];
        yield 'repère sans valeur' => [['about_content[journeyStats]' => ' | dans le numérique']];
        yield 'repère anglais mal formé' => [['about_content[journeyStatsEn]' => 'Since 2016']];
        yield 'trop de repères' => [['about_content[journeyStats]' => implode("\n", array_map(static fn (int $i): string => "$i | repère", range(1, 7)))]];
        yield 'valeur de repère trop longue' => [['about_content[journeyStats]' => str_repeat('a', 31).' | libellé']];
        yield 'libellé de repère trop long' => [['about_content[journeyStats]' => '2026 | '.str_repeat('a', 121)]];
        yield 'repère en UTF-8 invalide' => [['about_content[journeyStats]' => "2026 | x\xC3\x28"]];
        yield 'repère avec contrôle bidi' => [['about_content[journeyStats]' => "2026 | a\u{202E}b"]];
        yield 'vision trop longue' => [['about_content[visionPillar]' => str_repeat('a', AboutContent::PILLAR_MAX + 1)]];
        yield 'mission anglaise trop longue' => [['about_content[missionPillarEn]' => str_repeat('a', AboutContent::PILLAR_MAX + 1)]];
        yield 'vision avec NUL' => [['about_content[visionPillar]' => "a\0b"]];
        yield 'ambition avec contrôle bidi' => [['about_content[ambitionPillar]' => "Ambition \u{202E}etsuj"]];
        yield 'horizon avec espace de largeur nulle' => [['about_content[horizonMidText]' => "ab\u{200B}cd"]];
        yield 'formation avec BOM' => [['about_content[learningText]' => "x\u{FEFF}y"]];
        yield 'formation anglaise avec U+2028' => [['about_content[learningTextEn]' => "a\u{2028}b"]];
        yield 'vision en UTF-8 invalide' => [['about_content[visionPillar]' => "x\xC3\x28"]];
        // Champ obligatoire vidé (attribut HTML `required` contourné) : avant,
        // null arrivait dans un setter `string` => TypeError => 500.
        yield 'bio obligatoire vide' => [['about_content[bioP3]' => '']];
        yield 'titre obligatoire blanc' => [['about_content[heroTitle]' => '   ']];
    }

    /**
     * @param array<string, string> $override
     */
    #[DataProvider('invalidSubmissions')]
    public function testInvalidSubmissionIsAFormErrorNeverA500(array $override): void
    {
        $before = $this->storedVisionPillar();
        $this->submit($override + ['about_content[visionPillar]' => 'Ne doit pas être enregistré.']);

        self::assertSame(422, $this->client->getResponse()->getStatusCode(), 'Saisie invalide : 422, jamais 500 ni enregistrement.');
        self::assertSame($before, $this->storedVisionPillar());
    }

    public function testArraySubmittedToTheStatsFieldIsA422NeverA500(): void
    {
        $this->loginAdmin();
        $crawler = $this->client->request('GET', self::URL);
        $data = $crawler->filter('form[name="about_content"]')->form()->getPhpValues()['about_content'] ?? [];
        $data['journeyStats'] = ['x' => ['y']];
        $data['visionPillar'] = ['forgé'];
        $this->client->request('POST', self::URL, ['about_content' => $data]);

        self::assertContains($this->client->getResponse()->getStatusCode(), [422], 'Tableau forgé : 422, jamais 500.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function newFields(): iterable
    {
        foreach (self::NEW_FIELDS as $field) {
            yield $field => [$field];
            yield $field.'En' => [$field.'En'];
        }
    }

    /**
     * Chaque nouveau champ retiré de la soumission, avec un jeton valide :
     * jamais de 500 (null transmis au setter — leçon du contre-audit R1).
     */
    #[DataProvider('newFields')]
    public function testEachMissingNewFieldIsNeverA500(string $field): void
    {
        $this->loginAdmin();
        $crawler = $this->client->request('GET', self::URL);
        $data = $crawler->filter('form[name="about_content"]')->form()->getPhpValues()['about_content'] ?? [];
        foreach ($data as $key => $value) {
            if (\is_string($value) && '' === trim($value) && !str_ends_with($key, 'En') && !\in_array($key, self::NEW_FIELDS, true)) {
                $data[$key] = 'Valeur de test';
            }
        }
        unset($data[$field]);
        $this->client->request('POST', self::URL, ['about_content' => $data]);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains($status, [303, 422], "Champ « $field » absent : $status");
    }

    public function testNonAdminCannotEditThePage(): void
    {
        $this->client->loginUser($this->createUser(['ROLE_USER'], twoFactor: true), 'main');
        $this->client->request('GET', self::URL);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testUpdateRequiresAValidCsrfToken(): void
    {
        $this->loginAdmin();
        $crawler = $this->client->request('GET', self::URL);
        $data = $crawler->filter('form[name="about_content"]')->form()->getPhpValues()['about_content'] ?? [];
        $data['_token'] = 'jeton-forge';
        $data['visionPillar'] = 'Écrit sans jeton valide.';
        $this->client->request('POST', self::URL, ['about_content' => $data]);

        self::assertNotSame(303, $this->client->getResponse()->getStatusCode());
        self::assertNotSame('Écrit sans jeton valide.', $this->storedVisionPillar());
    }
}
