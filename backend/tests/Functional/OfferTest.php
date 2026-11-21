<?php

namespace App\Tests\Functional;

use App\Entity\Offer;

/**
 * Offres du site public (refonte 2026-11, lot R1) :
 *  - API publique en LECTURE SEULE, offres publiées uniquement ;
 *  - écriture exclusivement par le back-office (ROLE_ADMIN + 2FA, CSRF) ;
 *  - aucune saisie ne doit produire de 500 (leçon du lot B3).
 */
final class OfferTest extends FunctionalTestCase
{
    /** @var list<int> */
    private array $created = [];

    protected function tearDown(): void
    {
        if ([] !== $this->created) {
            $this->em()->getConnection()->executeStatement('DELETE FROM offer WHERE id IN (?)', [$this->created], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);
        }
        parent::tearDown();
    }

    private function offer(string $slug, bool $published, int $position = 500): Offer
    {
        $offer = (new Offer())
            ->setSlug($slug)->setPublished($published)->setPosition($position)
            ->setTitle('Titre '.$slug)->setShortTitle('Court')->setPain('Douleur ?')->setHeadline('Titre carrousel')
            ->setLeadText('Accroche.')->setCardText('Carte.')->setAudience('PME')->setClientNeeds('Vos textes.')->setDelay('7 jours')
            ->setPrice(90000)->setIncludes(['Un', 'Deux']);
        $this->em()->persist($offer);
        $this->em()->flush();
        $this->created[] = (int) $offer->getId();

        return $offer;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function apiOffers(): array
    {
        $response = $this->jsonRequest('GET', '/api/offers', server: ['HTTP_ACCEPT' => 'application/ld+json']);
        self::assertSame(200, $response->getStatusCode());
        /** @var list<array<string, mixed>> */
        return $this->json($response)['member'] ?? [];
    }

    private function slug(): string
    {
        return 'test-'.bin2hex(random_bytes(4));
    }

    // ---- API publique ------------------------------------------------------

    public function testApiListsOnlyPublishedOffersInOrder(): void
    {
        $later = $this->offer($this->slug(), true, 902);
        $first = $this->offer($this->slug(), true, 901);
        $draft = $this->offer($this->slug(), false, 900);

        $slugs = array_column($this->apiOffers(), 'slug');

        self::assertNotContains($draft->getSlug(), $slugs, 'Un brouillon ne doit jamais être exposé.');
        $i = array_search($first->getSlug(), $slugs, true);
        $j = array_search($later->getSlug(), $slugs, true);
        self::assertIsInt($i);
        self::assertIsInt($j);
        self::assertLessThan($j, $i, 'Les offres doivent être triées par position.');
    }

    public function testApiExposesOnlyPublicFields(): void
    {
        $offer = $this->offer($this->slug(), true);
        $items = array_values(array_filter($this->apiOffers(), static fn (array $o): bool => ($o['slug'] ?? null) === $offer->getSlug()));
        self::assertCount(1, $items);

        foreach (['published', 'createdAt', 'updatedAt'] as $hidden) {
            self::assertArrayNotHasKey($hidden, $items[0], "Le champ interne « $hidden » ne doit pas être exposé.");
        }
        self::assertSame(90000, $items[0]['price']);
        self::assertSame(['Un', 'Deux'], $items[0]['includes']);
    }

    public function testItemRouteNeverReturnsAnOffer(): void
    {
        $published = $this->offer($this->slug(), true);
        $draft = $this->offer($this->slug(), false);

        foreach ([$published, $draft] as $offer) {
            $status = $this->jsonRequest('GET', '/api/offers/'.$offer->getId())->getStatusCode();
            self::assertNotSame(200, $status, 'Aucune lecture élément : la liste suffit, et un brouillon ne doit jamais sortir.');
            self::assertLessThan(500, $status);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function writeAttempts(): iterable
    {
        yield 'POST anonyme' => ['POST', '/api/offers'];
        yield 'PUT anonyme' => ['PUT', '/api/offers/1'];
        yield 'PATCH anonyme' => ['PATCH', '/api/offers/1'];
        yield 'DELETE anonyme' => ['DELETE', '/api/offers/1'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('writeAttempts')]
    public function testApiRefusesEveryWrite(string $method, string $uri): void
    {
        $status = $this->jsonRequest($method, $uri, ['slug' => 'pirate', 'price' => 1, 'published' => true])->getStatusCode();
        self::assertContains($status, [404, 405], "$method $uri doit être refusé (aucune écriture par l'API).");
    }

    public function testApiRefusesWritesEvenForAnAdminJwt(): void
    {
        $jwt = $this->jwtFor($this->createUser(['ROLE_ADMIN'], twoFactor: true));
        $status = $this->jsonRequest('POST', '/api/offers', ['slug' => 'pirate', 'price' => 1, 'published' => true], $jwt)->getStatusCode();
        self::assertContains($status, [404, 405]);
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne("SELECT COUNT(*) FROM offer WHERE slug = 'pirate'"));
    }

    // ---- Back-office -------------------------------------------------------

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function nonAdmins(): iterable
    {
        yield 'utilisateur' => [['ROLE_USER']];
        yield 'éditeur' => [['ROLE_EDITOR']];
        yield 'modérateur' => [['ROLE_MODERATOR']];
        yield 'manager' => [['ROLE_MANAGER']];
    }

    /**
     * @param list<string> $roles
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('nonAdmins')]
    public function testNonAdminsCannotReachTheBackOffice(array $roles): void
    {
        $this->client->loginUser($this->createUser($roles, twoFactor: true), 'main');
        $offer = $this->offer($this->slug(), false);
        foreach (['/admin/offer/index', '/admin/offer/create', '/admin/offer/'.$offer->getId().'/update'] as $uri) {
            $this->client->request('GET', $uri);
            self::assertContains($this->client->getResponse()->getStatusCode(), [302, 403], "$uri doit être refusé à ".implode(',', $roles));
        }
        $this->client->request('POST', '/admin/offer/'.$offer->getId().'/delete', ['_token' => 'x']);
        self::assertSame(1, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM offer WHERE id = ?', [$offer->getId()]));
    }

    public function testAnonymousIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin/offer/index');
        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    /**
     * @param array<string, string> $override
     * @return array<string, mixed>
     */
    private function submitCreate(array $override): array
    {
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
        $crawler = $this->client->request('GET', '/admin/offer/create');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="offer"]')->form();
        $values = array_merge([
            'offer[slug]' => $this->slug(),
            'offer[title]' => 'Offre de test',
            'offer[shortTitle]' => 'Test',
            'offer[pain]' => 'Une douleur ?',
            'offer[headline]' => 'Votre test en 7 jours.',
            'offer[leadText]' => 'Une accroche.',
            'offer[cardText]' => 'Un bénéfice.',
            'offer[audience]' => 'PME',
            'offer[clientNeeds]' => 'Vos textes.',
            'offer[delay]' => '7 jours',
            'offer[price]' => '120000',
            'offer[includes]' => "Premier élément\nSecond élément",
            'offer[calendar]' => "J1 | Échange\nJ7 | Livraison",
            'offer[pack]' => 'Répondre : Questions 24 h sur 24 ; Réponses dans votre ton',
            'offer[category]' => 'dev',
            'offer[icon]' => 'web',
            'offer[previewType]' => 'screens',
            'offer[situation]' => 'clients',
        ], $override);
        $this->client->submit($form, $values);

        return $values;
    }

    public function testAdminCreatesAnOfferWithStructuredLists(): void
    {
        $values = $this->submitCreate([]);
        self::assertResponseRedirects('/admin/offer/index');

        $offer = $this->em()->getRepository(Offer::class)->findOneBy(['slug' => $values['offer[slug]']]);
        self::assertInstanceOf(Offer::class, $offer);
        $this->created[] = (int) $offer->getId();
        self::assertFalse($offer->isPublished(), 'Par défaut, une offre est un brouillon.');
        self::assertSame(['Premier élément', 'Second élément'], $offer->getIncludes());
        self::assertSame([['day' => 'J1', 'text' => 'Échange'], ['day' => 'J7', 'text' => 'Livraison']], $offer->getCalendar());
        self::assertSame([['title' => 'Répondre', 'items' => ['Questions 24 h sur 24', 'Réponses dans votre ton']]], $offer->getPack());
        self::assertNull($offer->getIncludesEn(), 'Une liste anglaise vide reste nulle (repli sur le français).');
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidSubmissions(): iterable
    {
        yield 'calendrier sans séparateur' => [['offer[calendar]' => 'J1 Échange']];
        yield 'pack sans élément' => [['offer[pack]' => 'Répondre :']];
        yield 'trop d\'éléments inclus' => [['offer[includes]' => implode("\n", array_map(static fn (int $i): string => "Élément $i", range(1, 13)))]];
        yield 'élément trop long' => [['offer[includes]' => str_repeat('a', 201)]];
        yield 'liste vide' => [['offer[includes]' => '']];
        yield 'jour de calendrier trop long' => [['offer[calendar]' => str_repeat('J', 11).' | texte']];
        yield 'slug invalide' => [['offer[slug]' => 'Slug Avec Espaces']];
        yield 'slug avec saut de ligne interne' => [['offer[slug]' => "ab\ncd"]];
        yield 'prix négatif' => [['offer[price]' => '-1']];
        yield 'prix énorme' => [['offer[price]' => '99999999999999999999']];
        yield 'prix non numérique' => [['offer[price]' => 'gratuit']];
        yield 'titre trop long' => [['offer[title]' => str_repeat('a', 121)]];
        yield 'texte énorme' => [['offer[includes]' => str_repeat("a\n", 15000)]];
        // Lot de suivi R1 : l'UTF-8 invalide vidait la liste en silence (303).
        yield 'calendrier en UTF-8 invalide' => [['offer[calendar]' => "J1 | x\xC3\x28"]];
        yield 'pack en UTF-8 invalide' => [['offer[pack]' => "Répondre : x\xC3\x28"]];
        yield 'liste anglaise en UTF-8 invalide' => [['offer[includesEn]' => "x\xC3\x28"]];
        yield 'titre avec NUL' => [['offer[title]' => "a\0b"]];
        yield 'titre avec contrôle bidi' => [['offer[title]' => "Offre \u{202E}etsuj"]];
        yield 'accroche avec espace de largeur nulle' => [['offer[leadText]' => "ab\u{200B}cd"]];
        yield 'élément avec BOM' => [['offer[includes]' => "x\u{FEFF}y"]];
    }

    /**
     * @param array<string, string> $override
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidSubmissions')]
    public function testInvalidSubmissionIsAFormErrorNeverA500(array $override): void
    {
        $values = $this->submitCreate($override);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertSame(422, $status, 'Saisie invalide : formulaire réaffiché en 422, jamais 500 ni enregistrement.');
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM offer WHERE slug = ?', [$values['offer[slug]']]));
    }

    public function testChoiceFieldsRejectForgedValues(): void
    {
        // Le crawler refuse une valeur hors liste : on forge la requête à la main.
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
        $crawler = $this->client->request('GET', '/admin/offer/create');
        $token = (string) $crawler->filter('input[name="offer[_token]"]')->attr('value');
        $slug = $this->slug();

        $this->client->request('POST', '/admin/offer/create', ['offer' => [
            '_token' => $token, 'slug' => $slug, 'title' => 'T', 'shortTitle' => 'T', 'pain' => 'P', 'headline' => 'H',
            'leadText' => 'L', 'cardText' => 'C', 'audience' => 'A', 'clientNeeds' => 'N', 'delay' => 'D', 'price' => '1',
            'includes' => 'Un', 'category' => 'evil', 'icon' => '<script>', 'previewType' => 'chat', 'situation' => 'clients',
        ]]);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM offer WHERE slug = ?', [$slug]));
    }

    public function testArraySubmittedToAListFieldIsA422NeverA500(): void
    {
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
        $crawler = $this->client->request('GET', '/admin/offer/create');
        $token = (string) $crawler->filter('input[name="offer[_token]"]')->attr('value');
        $slug = $this->slug();

        $this->client->request('POST', '/admin/offer/create', ['offer' => [
            '_token' => $token, 'slug' => $slug, 'title' => 'T', 'shortTitle' => 'T', 'pain' => 'P', 'headline' => 'H',
            'leadText' => 'L', 'cardText' => 'C', 'audience' => 'A', 'clientNeeds' => 'N', 'delay' => 'D', 'price' => '1',
            'includes' => ['x' => ['y']], 'calendar' => ['J1'], 'category' => 'dev', 'icon' => 'web', 'previewType' => 'chat', 'situation' => 'clients',
        ]]);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM offer WHERE slug = ?', [$slug]));
    }

    public function testLegitimateUnicodeIsAccepted(): void
    {
        $values = $this->submitCreate([
            'offer[title]' => 'Prise de rendez‑vous 👨‍💻 — « ça marche »',
            'offer[leadText]' => "Première ligne.\nSeconde ligne.",
        ]);
        self::assertResponseRedirects('/admin/offer/index');
        $offer = $this->em()->getRepository(Offer::class)->findOneBy(['slug' => $values['offer[slug]']]);
        self::assertInstanceOf(Offer::class, $offer);
        $this->created[] = (int) $offer->getId();
    }

    /**
     * Lot de suivi R1 : un titre de bloc avec « : » ou un élément avec « ; »
     * (écrit hors formulaire) était défiguré au premier enregistrement.
     *
     * @return iterable<string, array{list<array{title: string, items: list<string>}>}>
     */
    public static function packsBreakingTheRoundTrip(): iterable
    {
        yield 'deux-points dans le titre' => [[['title' => 'A: B', 'items' => ['x']]]];
        yield 'point-virgule dans un élément' => [[['title' => 'A', 'items' => ['x ; y']]]];
    }

    /**
     * @param list<array{title: string, items: list<string>}> $pack
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('packsBreakingTheRoundTrip')]
    public function testPackSeparatorsAreRejectedByTheEntity(array $pack): void
    {
        /** @var \Symfony\Component\Validator\Validator\ValidatorInterface $validator */
        $validator = static::getContainer()->get(\Symfony\Component\Validator\Validator\ValidatorInterface::class);
        self::assertGreaterThan(0, \count($validator->validateProperty((new Offer())->setPack($pack), 'pack')));
    }

    public function testCalendarDayCannotContainThePipeSeparator(): void
    {
        /** @var \Symfony\Component\Validator\Validator\ValidatorInterface $validator */
        $validator = static::getContainer()->get(\Symfony\Component\Validator\Validator\ValidatorInterface::class);
        self::assertGreaterThan(0, \count($validator->validateProperty((new Offer())->setCalendar([['day' => 'J1|J2', 'text' => 'x']]), 'calendar')));
    }

    public function testEverySeededOfferStillValidatesAndRoundTripsUnchanged(): void
    {
        // Les 7 offres de la migration doivent rester valides sous les
        // nouvelles règles, et l'enregistrement sans modification par le
        // formulaire ne doit rien altérer (aller-retour fidèle).
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
        $conn = $this->em()->getConnection();
        $ids = $conn->fetchFirstColumn("SELECT id FROM offer WHERE created_at = '2026-11-21 14:00:00'");
        self::assertCount(7, $ids);
        $columns = 'slug, title, includes, before_items, after_items, calendar, pack, price, hero_position, published';

        foreach ($ids as $id) {
            $before = $conn->fetchAssociative("SELECT $columns FROM offer WHERE id = ?", [$id]);
            $crawler = $this->client->request('GET', "/admin/offer/$id/update");
            self::assertResponseIsSuccessful();
            $this->client->submit($crawler->filter('form[name="offer"]')->form());
            self::assertResponseRedirects('/admin/offer/index', null, "L'offre $id doit rester valide.");
            self::assertSame($before, $conn->fetchAssociative("SELECT $columns FROM offer WHERE id = ?", [$id]), "L'offre $id ne doit pas être altérée.");
        }
    }

    public function testUpdateRequiresAValidCsrfToken(): void
    {
        $offer = $this->offer($this->slug(), false);
        $id = $offer->getId();
        $title = $offer->getTitle(); // lu AVANT : le formulaire modifie l'objet en mémoire (noyau partagé)
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
        $this->client->request('POST', '/admin/offer/'.$id.'/update', ['offer' => ['_token' => 'faux', 'title' => 'Piraté']]);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        self::assertSame($title, $this->em()->getConnection()->fetchOne('SELECT title FROM offer WHERE id = ?', [$id]), 'Rien ne doit être écrit en base sans jeton CSRF valide.');
    }

    public function testAdminWithoutTwoFactorIsSentToTwoFactorSetup(): void
    {
        $this->client->loginUser($this->createUser(['ROLE_ADMIN']), 'main');
        foreach (['/admin/offer/index', '/admin/offer/create'] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseRedirects();
            self::assertStringContainsString('2fa', (string) $this->client->getResponse()->headers->get('Location'));
        }
    }

    public function testCreateWithoutCsrfTokenIsRejected(): void
    {
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
        $slug = $this->slug();
        $this->client->request('POST', '/admin/offer/create', ['offer' => ['slug' => $slug, 'title' => 'T', 'includes' => 'Un', 'price' => '1']]);

        self::assertSame(422, $this->client->getResponse()->getStatusCode(), 'Sans jeton CSRF : formulaire réaffiché, rien d\'enregistré.');
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM offer WHERE slug = ?', [$slug]));
    }

    public function testDeleteRequiresAValidCsrfToken(): void
    {
        $offer = $this->offer($this->slug(), false);
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');

        $this->client->request('POST', '/admin/offer/'.$offer->getId().'/delete', ['_token' => 'faux']);
        self::assertSame(1, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM offer WHERE id = ?', [$offer->getId()]), 'Sans jeton valide, rien n\'est supprimé.');

        $crawler = $this->client->request('GET', '/admin/offer/index');
        $token = (string) $crawler->filter(sprintf('form[action$="/admin/offer/%d/delete"] input[name="_token"]', $offer->getId()))->attr('value');
        $this->client->request('POST', '/admin/offer/'.$offer->getId().'/delete', ['_token' => $token]);
        self::assertResponseRedirects('/admin/offer/index');
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM offer WHERE id = ?', [$offer->getId()]));
    }

    public function testTextIsEscapedInTheBackOfficeList(): void
    {
        $offer = $this->offer($this->slug(), false);
        $offer->setTitle('<img src=x onerror=alert(1)>');
        $this->em()->flush();
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');

        $this->client->request('GET', '/admin/offer/index');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    public function testDraftBecomesVisibleOnlyOncePublished(): void
    {
        $offer = $this->offer($this->slug(), false);
        self::assertNotContains($offer->getSlug(), array_column($this->apiOffers(), 'slug'));

        $offer->setPublished(true);
        $this->em()->flush();
        self::assertContains($offer->getSlug(), array_column($this->apiOffers(), 'slug'));
    }
}
