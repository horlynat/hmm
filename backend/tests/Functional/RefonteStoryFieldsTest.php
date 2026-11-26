<?php

namespace App\Tests\Functional;

use App\Entity\AboutContent;
use App\Entity\Article;
use App\Entity\Offer;
use App\Entity\Project;
use App\Entity\ProjectInfo;
use App\Entity\Tag;
use App\Enum\ProjectStatusEnum;
use App\Repository\AboutContentRepository;

/**
 * Refonte (copie du prototype) : contenus éditoriaux ajoutés au back-office.
 *
 *  - Étude de cas d'un projet (ProjectInfo) : résultat, bref en trois temps,
 *    point de départ, démarche, livrables, légendes de galerie, projet
 *    discret, offre liée — FR et EN.
 *  - Article : chapô (FR/EN), offre liée, date de publication publique.
 *  - À propos : 5e différenciateur, facultatif.
 *
 * Chemin 3 (attaque) : saisies mal formées -> erreur de formulaire, jamais de
 * 500 ; offre liée hors liste refusée ; aucun champ admin exposé en trop.
 */
final class RefonteStoryFieldsTest extends FunctionalTestCase
{
    /** @var list<object> */
    private array $created = [];

    /** @var array<string, mixed> */
    private array $aboutBackup = [];
    /** @var list<array<string, mixed>> */
    private array $aboutTranslations = [];
    private int $aboutId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aboutId = (int) static::getContainer()->get(AboutContentRepository::class)->getContent()->getId();
        $conn = $this->em()->getConnection();
        $this->aboutBackup = $conn->fetchAssociative('SELECT * FROM about_content WHERE id = ?', [$this->aboutId]) ?: [];
        $this->aboutTranslations = $conn->fetchAllAssociative("SELECT entity_type, entity_id, field, locale, value FROM translation WHERE entity_type = 'AboutContent' AND entity_id = ?", [$this->aboutId]);
    }

    protected function tearDown(): void
    {
        $conn = $this->em()->getConnection();
        $row = $this->aboutBackup;
        unset($row['id']);
        if ([] !== $row) {
            $conn->update('about_content', $row, ['id' => $this->aboutId]);
        }
        $conn->executeStatement("DELETE FROM translation WHERE entity_type = 'AboutContent' AND entity_id = ?", [$this->aboutId]);
        foreach ($this->aboutTranslations as $t) {
            $conn->insert('translation', $t);
        }
        $this->em()->clear();
        foreach (array_reverse($this->created) as $entity) {
            $managed = $this->em()->find($entity::class, $entity->getId());
            if (null !== $managed) {
                $this->em()->remove($managed);
            }
        }
        $this->em()->flush();
        parent::tearDown();
    }

    private function loginAdmin(): void
    {
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
    }

    private function offer(string $slug, bool $published = true): Offer
    {
        $offer = (new Offer())
            ->setSlug($slug)->setPublished($published)->setPosition(900)
            ->setTitle('Titre '.$slug)->setShortTitle('Court')->setPain('Douleur ?')->setHeadline('Titre carrousel')
            ->setLeadText('Accroche.')->setCardText('Carte.')->setAudience('PME')->setClientNeeds('Vos textes.')->setDelay('7 jours')
            ->setPrice(90000)->setIncludes(['Un', 'Deux']);
        $this->em()->persist($offer);
        $this->em()->flush();
        $this->created[] = $offer;

        return $offer;
    }

    private function project(): Project
    {
        $owner = $this->createUser(['ROLE_ADMIN']);
        $project = (new Project())
            ->setTitle('Étude de cas '.bin2hex(random_bytes(3)))
            ->setDescription('Une description de test suffisamment longue.')
            ->setLink('https://example.com')
            ->setOwner($owner)
            ->setStatus(ProjectStatusEnum::IN_PROGRESS);
        $project->setSlug('etude-'.bin2hex(random_bytes(4)));
        $this->em()->persist($project);
        $this->em()->flush();
        $this->created[] = $project;

        return $project;
    }

    /** @param array<string, string|bool> $values */
    private function submitProject(Project $project, array $values): void
    {
        $this->loginAdmin();
        $crawler = $this->client->request('GET', '/admin/project/'.$project->getId().'/update');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="project"]')->form();
        foreach ($values as $name => $value) {
            if (\is_bool($value)) {
                $checkbox = $form["project[$name]"];
                self::assertInstanceOf(\Symfony\Component\DomCrawler\Field\ChoiceFormField::class, $checkbox);
                $value ? $checkbox->tick() : $checkbox->untick();
                continue;
            }
            $form["project[$name]"] = $value;
        }
        $this->client->submit($form);
    }

    // ------------------------------------------------------------------ Projet

    public function testAdminSavesTheCaseStudyStoryInBothLanguagesAndTheApiExposesIt(): void
    {
        $this->offer('offre-liee-test');
        $project = $this->project();

        $this->submitProject($project, [
            'result' => 'Une société opérationnelle dès son lancement.',
            'resultEn' => 'A company up and running from day one.',
            'brief' => "La situation | Une société à lancer.\nCe que j'ai fait | Toute la plateforme.\nCe que cela a changé | Un seul outil.",
            'context' => 'Le contexte.',
            'stake' => 'Le défi.',
            'steps' => "Cadrage | Lister les publics.\nConception | Un outil unique.",
            'stepsEn' => 'Scoping | List the audiences.',
            'solution' => 'Une plateforme unique.',
            'deliverables' => "Site public\n\nEspace client",
            'galleryCaptions' => "Site public de la société\nEspace client",
            'discreet' => true,
            'relatedOfferSlug' => 'offre-liee-test',
        ]);
        self::assertResponseRedirects('/admin/project/index');

        $this->em()->clear();
        $info = $this->em()->find(Project::class, $project->getId())?->getInfo();
        self::assertInstanceOf(ProjectInfo::class, $info);
        self::assertSame('Une société opérationnelle dès son lancement.', $info->getResult());
        self::assertSame('A company up and running from day one.', $info->getResultEn());
        // assertEquals : MySQL réordonne les clés des objets JSON.
        self::assertEquals([
            ['title' => 'La situation', 'text' => 'Une société à lancer.'],
            ['title' => "Ce que j'ai fait", 'text' => 'Toute la plateforme.'],
            ['title' => 'Ce que cela a changé', 'text' => 'Un seul outil.'],
        ], $info->getBrief());
        self::assertEquals([['title' => 'Scoping', 'text' => 'List the audiences.']], $info->getStepsEn());
        self::assertSame(['Site public', 'Espace client'], $info->getDeliverables(), 'Les lignes vides sont ignorées.');
        self::assertNull($info->getDeliverablesEn(), 'Traduction vide -> null (repli sur le français).');
        self::assertTrue($info->isDiscreet());
        self::assertSame('offre-liee-test', $info->getRelatedOfferSlug());

        $response = $this->jsonRequest('GET', '/api/projects/'.$project->getId(), server: ['HTTP_ACCEPT' => 'application/ld+json']);
        self::assertSame(200, $response->getStatusCode());
        $api = $this->json($response)['info'] ?? [];
        self::assertSame('Une société opérationnelle dès son lancement.', $api['result'] ?? null);
        self::assertSame('Le défi.', $api['stake'] ?? null);
        self::assertSame(['Site public de la société', 'Espace client'], $api['galleryCaptions'] ?? null);
        self::assertTrue($api['discreet'] ?? null);
        self::assertSame('offre-liee-test', $api['relatedOfferSlug'] ?? null);
    }

    public function testEditingPrefillsTheStoryFieldsAndAResubmitIsLossless(): void
    {
        $project = $this->project();
        $this->submitProject($project, [
            'result' => 'Résultat.',
            'brief' => 'La situation | Texte.',
            'steps' => 'Cadrage | Texte.',
            'deliverables' => "Un\nDeux",
            'discreet' => true,
        ]);
        self::assertResponseRedirects();

        // Aller-retour sans modification : rien ne doit être perdu.
        $this->submitProject($project, []);
        self::assertResponseRedirects();
        $this->em()->clear();
        $info = $this->em()->find(Project::class, $project->getId())?->getInfo();
        self::assertInstanceOf(ProjectInfo::class, $info);
        self::assertSame('Résultat.', $info->getResult());
        self::assertEquals([['title' => 'La situation', 'text' => 'Texte.']], $info->getBrief());
        self::assertSame(['Un', 'Deux'], $info->getDeliverables());
        self::assertTrue($info->isDiscreet());
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function invalidProjectStory(): iterable
    {
        yield 'bref de 4 lignes' => [['brief' => "A | a\nB | b\nC | c\nD | d"], 'brief'];
        yield 'étape sans séparateur' => [['steps' => 'Cadrage sans texte'], 'steps'];
        yield 'bref EN sans séparateur' => [['briefEn' => 'Situation only'], 'briefEn'];
        // Contre-audit : « Titre | » serait stocké sans texte puis pré-rempli
        // sans « | » — projet impossible à réenregistrer ensuite.
        yield 'étape sans texte après le séparateur' => [['steps' => 'Cadrage |'], 'steps'];
        yield 'bref sans titre' => [['brief' => '| Un texte sans titre'], 'brief'];
        yield 'résultat trop long' => [['result' => str_repeat('x', 256)], 'result'];
        yield 'contexte trop long' => [['context' => str_repeat('x', 2001)], 'context'];
    }

    /** @param array<string, string> $values */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidProjectStory')]
    public function testMalformedStoryIsAFormErrorNeverA500(array $values, string $field): void
    {
        $project = $this->project();
        $this->submitProject($project, $values);
        // Le contrôleur projet ré-affiche le formulaire (200) avec l'erreur,
        // sans rediriger ni enregistrer.
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#project_'.$field.' ~ *, .text-danger, .field-error');
        $this->em()->clear();
        self::assertNull($this->em()->find(Project::class, $project->getId())?->getInfo(), 'Rien n\'est enregistré.');
    }

    public function testRelatedOfferOutsideTheListIsRejected(): void
    {
        $project = $this->project();
        $this->loginAdmin();
        $crawler = $this->client->request('GET', '/admin/project/'.$project->getId().'/update');
        $form = $crawler->filter('form[name="project"]')->form();
        $values = $form->getPhpValues();
        $values['project']['relatedOfferSlug'] = 'javascript:alert(1)';
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseIsSuccessful(); // formulaire ré-affiché, pas de redirection
        self::assertStringContainsString('relatedOfferSlug', (string) $this->client->getResponse()->getContent());
        $this->em()->clear();
        self::assertNull($this->em()->find(Project::class, $project->getId())?->getInfo());
    }

    public function testUnpublishedOfferCannotBeLinked(): void
    {
        $this->offer('offre-brouillon-test', published: false);
        $project = $this->project();
        $this->loginAdmin();
        $crawler = $this->client->request('GET', '/admin/project/'.$project->getId().'/update');
        self::assertStringNotContainsString('offre-brouillon-test', (string) $this->client->getResponse()->getContent());
        $form = $crawler->filter('form[name="project"]')->form();
        $values = $form->getPhpValues();
        $values['project']['relatedOfferSlug'] = 'offre-brouillon-test';
        $this->client->request('POST', $form->getUri(), $values);
        self::assertFalse($this->client->getResponse()->isRedirect(), 'Offre non publiée refusée.');
        $this->em()->clear();
        self::assertNull($this->em()->find(Project::class, $project->getId())?->getInfo());
    }

    // ----------------------------------------------------------------- Article

    public function testArticleSummaryRelatedOfferAndDateArePublic(): void
    {
        $this->offer('offre-article-test');
        $tag = (new Tag())->setName('Tag refonte '.bin2hex(random_bytes(3)));
        $this->em()->persist($tag);
        $this->em()->flush();
        $this->created[] = $tag;

        $this->loginAdmin();
        $crawler = $this->client->request('GET', '/admin/article/create');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="article"]')->form([
            'article[title]' => 'Article refonte '.bin2hex(random_bytes(3)),
            'article[content]' => '<h2>Le constat</h2><p>Un contenu suffisamment long.</p>',
            'article[summary]' => '  Un chapô <b>pas</b> du HTML.  ',
            'article[summaryEn]' => 'An English summary.',
            'article[relatedOfferSlug]' => 'offre-article-test',
        ]);
        $tags = $form['article[tags]'];
        self::assertInstanceOf(\Symfony\Component\DomCrawler\Field\ChoiceFormField::class, $tags);
        $tags->select([(string) $tag->getId()]);
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/article/index');

        $this->em()->clear();
        $article = $this->em()->getRepository(Article::class)->findOneBy(['relatedOfferSlug' => 'offre-article-test']);
        self::assertInstanceOf(Article::class, $article);
        $this->created[] = $article;
        self::assertSame('Un chapô <b>pas</b> du HTML.', $article->getSummary(), 'Stocké tel quel (affiché échappé côté site), espaces retirés.');
        self::assertSame('An English summary.', $article->getSummaryEn());

        $response = $this->jsonRequest('GET', '/api/articles/'.$article->getId(), server: ['HTTP_ACCEPT' => 'application/ld+json']);
        self::assertSame(200, $response->getStatusCode());
        $api = $this->json($response);
        self::assertSame('Un chapô <b>pas</b> du HTML.', $api['summary'] ?? null);
        self::assertSame('An English summary.', $api['summaryEn'] ?? null);
        self::assertSame('offre-article-test', $api['relatedOfferSlug'] ?? null);
        self::assertNotEmpty($api['publishedAt'] ?? null);
        self::assertArrayNotHasKey('author', $api);
    }

    public function testArticleSummaryTooLongIsAFormError(): void
    {
        $tag = (new Tag())->setName('Tag refonte '.bin2hex(random_bytes(3)));
        $this->em()->persist($tag);
        $this->em()->flush();
        $this->created[] = $tag;

        $this->loginAdmin();
        $crawler = $this->client->request('GET', '/admin/article/create');
        $form = $crawler->filter('form[name="article"]')->form([
            'article[title]' => 'Article trop long '.bin2hex(random_bytes(3)),
            'article[content]' => '<p>Un contenu suffisamment long.</p>',
            'article[summary]' => str_repeat('x', 401),
        ]);
        $tags = $form['article[tags]'];
        self::assertInstanceOf(\Symfony\Component\DomCrawler\Field\ChoiceFormField::class, $tags);
        $tags->select([(string) $tag->getId()]);
        $this->client->submit($form);
        self::assertLessThan(500, $this->client->getResponse()->getStatusCode());
        self::assertFalse($this->client->getResponse()->isRedirect(), 'Formulaire ré-affiché, pas enregistré.');
        self::assertStringContainsString('400', (string) $this->client->getResponse()->getContent());
        self::assertNull($this->em()->getRepository(Article::class)->findOneBy(['summary' => str_repeat('x', 401)]));
    }

    // ----------------------------------------------------------------- À propos

    public function testFifthDifferentiatorIsOptionalAndExposed(): void
    {
        $this->loginAdmin();
        $crawler = $this->client->request('GET', '/admin/content/about/');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="about_content"]')->form();
        $values = [];
        foreach ($form->all() as $name => $field) {
            $short = preg_replace('/^about_content\[(.+)\]$/', '$1', $name);
            if (\in_array($short, ['_token', 'profileImageFile'], true) || str_ends_with((string) $short, 'En') || str_starts_with((string) $short, 'why5')) {
                continue;
            }
            if ('' === trim((string) $field->getValue())) {
                // Base vierge (CI) : les repères exigent « valeur | libellé ».
                $values[$name] = 'journeyStats' === $short ? '2016 | dans le numérique' : 'Valeur de test';
            }
        }

        // Vide : accepté (la fiche de production n'a pas de 5e point).
        $this->client->submit($form, $values + ['about_content[why5Title]' => '', 'about_content[why5Desc]' => '']);
        self::assertResponseRedirects('/admin/content/about/');
        $this->em()->clear();
        self::assertEmpty(static::getContainer()->get(AboutContentRepository::class)->getContent()->getWhy5Title(), 'Vide (chaîne vide ou null) : le site masque le point.');

        $crawler = $this->client->request('GET', '/admin/content/about/');
        $form = $crawler->filter('form[name="about_content"]')->form();
        $this->client->submit($form, [
            'about_content[why5Title]' => 'Sens du design, pas seulement du code',
            'about_content[why5TitleEn]' => 'An eye for design, not just code',
            'about_content[why5Desc]' => 'Formé au web design.',
        ]);
        self::assertResponseRedirects('/admin/content/about/');

        $response = $this->jsonRequest('GET', '/api/about_contents', server: ['HTTP_ACCEPT' => 'application/ld+json']);
        $item = $this->json($response)['member'][0] ?? [];
        self::assertSame('Sens du design, pas seulement du code', $item['why5Title'] ?? null);
        self::assertSame('An eye for design, not just code', $item['why5TitleEn'] ?? null);
        self::assertSame('Formé au web design.', $item['why5Desc'] ?? null);
        self::assertInstanceOf(AboutContent::class, static::getContainer()->get(AboutContentRepository::class)->getContent());
    }
}
