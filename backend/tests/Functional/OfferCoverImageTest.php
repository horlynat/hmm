<?php

namespace App\Tests\Functional;

use App\Entity\Media;
use App\Entity\Offer;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Image illustrative réelle d'une offre (App\Entity\Offer::coverImage),
 * ajoutée au-dessus de l'illustration dessinée existante (previewType) —
 * même mécanisme que ProjectInfo::coverImage (AdminProjectController).
 */
final class OfferCoverImageTest extends FunctionalTestCase
{
    /** @var list<int> */
    private array $offerIds = [];

    /** @var list<string> Fichiers déjà présents avant le test (jamais supprimés). */
    private array $filesBefore = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesBefore = $this->uploadedFiles();
    }

    protected function tearDown(): void
    {
        $conn = $this->em()->getConnection();
        if ([] !== $this->offerIds) {
            $conn->executeStatement('DELETE FROM offer WHERE id IN (?)', [$this->offerIds], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);
        }
        // Tout ce que le test a téléversé — y compris l'image remplacée, que
        // l'offre ne référence plus : fichier sur disque ET ligne `media`.
        $created = array_values(array_diff($this->uploadedFiles(), $this->filesBefore));
        if ([] !== $created) {
            $paths = array_map(static fn (string $f): string => '/uploads/offers/'.basename($f), $created);
            $conn->executeStatement('DELETE FROM media WHERE file_path IN (?)', [$paths], [\Doctrine\DBAL\ArrayParameterType::STRING]);
            array_map('unlink', $created);
        }
        parent::tearDown();
    }

    /** @return list<string> */
    private function uploadedFiles(): array
    {
        $dir = static::getContainer()->getParameter('app.upload_dir').'/offers';

        return glob($dir.'/*') ?: [];
    }

    private function svgFile(string $name = 'photo.svg'): UploadedFile
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'offer-cover');
        file_put_contents($tmp, '<svg xmlns="http://www.w3.org/2000/svg"><circle r="4"/></svg>');

        return new UploadedFile($tmp, $name, 'image/svg+xml', null, true);
    }

    private function slug(): string
    {
        return 'test-'.bin2hex(random_bytes(4));
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function baseFields(array $override = []): array
    {
        return array_merge([
            'slug' => $this->slug(), 'title' => 'Offre de test', 'shortTitle' => 'Test', 'pain' => 'Une douleur ?',
            'headline' => 'Votre test en 7 jours.', 'leadText' => 'Une accroche.', 'cardText' => 'Un bénéfice.',
            'audience' => 'PME', 'clientNeeds' => 'Vos textes.', 'delay' => '7 jours', 'price' => '120000',
            'includes' => 'Premier élément', 'category' => 'dev', 'icon' => 'web', 'previewType' => 'screens', 'situation' => 'clients',
        ], $override);
    }

    public function testAdminUploadsACoverImageOnCreate(): void
    {
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
        $crawler = $this->client->request('GET', '/admin/offer/create');
        $token = (string) $crawler->filter('input[name="offer[_token]"]')->attr('value');
        $fields = $this->baseFields(['_token' => $token]);

        $this->client->request('POST', '/admin/offer/create', ['offer' => $fields], ['offer' => ['coverImage' => $this->svgFile()]]);

        self::assertResponseRedirects('/admin/offer/index');
        $offer = $this->em()->getRepository(Offer::class)->findOneBy(['slug' => $fields['slug']]);
        self::assertInstanceOf(Offer::class, $offer);
        $this->offerIds[] = (int) $offer->getId();

        $cover = $offer->getCoverImage();
        self::assertInstanceOf(Media::class, $cover);
        self::assertStringStartsWith('/uploads/offers/', $cover->getFilePath());
        self::assertSame($offer->getTitle(), $cover->getAltText());
    }

    public function testCoverImageIsExposedByThePublicApiOnlyOncePublished(): void
    {
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
        $crawler = $this->client->request('GET', '/admin/offer/create');
        $token = (string) $crawler->filter('input[name="offer[_token]"]')->attr('value');
        $fields = $this->baseFields(['_token' => $token, 'published' => '1']);

        $this->client->request('POST', '/admin/offer/create', ['offer' => $fields], ['offer' => ['coverImage' => $this->svgFile()]]);
        self::assertResponseRedirects('/admin/offer/index');
        $offer = $this->em()->getRepository(Offer::class)->findOneBy(['slug' => $fields['slug']]);
        self::assertInstanceOf(Offer::class, $offer);
        $this->offerIds[] = (int) $offer->getId();

        $response = $this->jsonRequest('GET', '/api/offers', server: ['HTTP_ACCEPT' => 'application/ld+json']);
        $items = array_values(array_filter($this->json($response)['member'] ?? [], static fn (array $o): bool => ($o['slug'] ?? null) === $fields['slug']));
        self::assertCount(1, $items);
        self::assertStringStartsWith('/uploads/offers/', $items[0]['coverImage']['filePath'] ?? '');
    }

    public function testReplacingTheCoverImageOnUpdatePointsToTheNewFile(): void
    {
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
        $crawler = $this->client->request('GET', '/admin/offer/create');
        $token = (string) $crawler->filter('input[name="offer[_token]"]')->attr('value');
        $fields = $this->baseFields(['_token' => $token]);
        $this->client->request('POST', '/admin/offer/create', ['offer' => $fields], ['offer' => ['coverImage' => $this->svgFile('premiere.svg')]]);
        $offer = $this->em()->getRepository(Offer::class)->findOneBy(['slug' => $fields['slug']]);
        self::assertInstanceOf(Offer::class, $offer);
        $id = (int) $offer->getId();
        $this->offerIds[] = $id;
        $firstPath = $offer->getCoverImage()?->getFilePath();

        $crawler = $this->client->request('GET', "/admin/offer/$id/update");
        $updateToken = (string) $crawler->filter('input[name="offer[_token]"]')->attr('value');
        $this->client->request('POST', "/admin/offer/$id/update", ['offer' => $this->baseFields(['_token' => $updateToken, 'slug' => $fields['slug']])], ['offer' => ['coverImage' => $this->svgFile('seconde.svg')]]);
        self::assertResponseRedirects('/admin/offer/index');

        $this->em()->clear();
        $reloaded = $this->em()->getRepository(Offer::class)->find($id);
        self::assertInstanceOf(Offer::class, $reloaded);
        $secondPath = $reloaded->getCoverImage()?->getFilePath();
        self::assertNotNull($secondPath);
        self::assertNotSame($firstPath, $secondPath, 'Un nouvel envoi doit remplacer le fichier précédent.');
    }

    public function testRemovingTheCoverImageViaTheCheckbox(): void
    {
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
        $crawler = $this->client->request('GET', '/admin/offer/create');
        $token = (string) $crawler->filter('input[name="offer[_token]"]')->attr('value');
        $fields = $this->baseFields(['_token' => $token]);
        $this->client->request('POST', '/admin/offer/create', ['offer' => $fields], ['offer' => ['coverImage' => $this->svgFile()]]);
        $offer = $this->em()->getRepository(Offer::class)->findOneBy(['slug' => $fields['slug']]);
        self::assertInstanceOf(Offer::class, $offer);
        $id = (int) $offer->getId();
        $this->offerIds[] = $id;
        self::assertNotNull($offer->getCoverImage());

        $crawler = $this->client->request('GET', "/admin/offer/$id/update");
        $updateToken = (string) $crawler->filter('input[name="offer[_token]"]')->attr('value');
        $this->client->request('POST', "/admin/offer/$id/update", ['offer' => $this->baseFields(['_token' => $updateToken, 'slug' => $fields['slug'], 'removeCoverImage' => '1'])]);
        self::assertResponseRedirects('/admin/offer/index');

        $this->em()->clear();
        $reloaded = $this->em()->getRepository(Offer::class)->find($id);
        self::assertInstanceOf(Offer::class, $reloaded);
        self::assertNull($reloaded->getCoverImage(), 'La case « Retirer » doit vider coverImage quand aucun nouveau fichier n\'est envoyé.');
    }

    public function testADisallowedFileTypeIsAFormErrorNeverA500(): void
    {
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
        $crawler = $this->client->request('GET', '/admin/offer/create');
        $token = (string) $crawler->filter('input[name="offer[_token]"]')->attr('value');
        $fields = $this->baseFields(['_token' => $token]);

        $tmp = (string) tempnam(sys_get_temp_dir(), 'offer-cover');
        file_put_contents($tmp, '#!/bin/sh\necho hi');
        $exe = new UploadedFile($tmp, 'script.sh', 'application/x-sh', null, true);

        $this->client->request('POST', '/admin/offer/create', ['offer' => $fields], ['offer' => ['coverImage' => $exe]]);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM offer WHERE slug = ?', [$fields['slug']]));
    }

    public function testCreatingAnOfferWithoutAnyFileNeverRequiresOne(): void
    {
        $this->client->loginUser($this->createUser(['ROLE_ADMIN'], twoFactor: true), 'main');
        $crawler = $this->client->request('GET', '/admin/offer/create');
        $token = (string) $crawler->filter('input[name="offer[_token]"]')->attr('value');
        $fields = $this->baseFields(['_token' => $token]);

        $this->client->request('POST', '/admin/offer/create', ['offer' => $fields]);

        self::assertResponseRedirects('/admin/offer/index');
        $offer = $this->em()->getRepository(Offer::class)->findOneBy(['slug' => $fields['slug']]);
        self::assertInstanceOf(Offer::class, $offer);
        $this->offerIds[] = (int) $offer->getId();
        self::assertNull($offer->getCoverImage());
    }
}
