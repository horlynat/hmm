<?php

namespace App\Tests\Entity;

use App\Service\EmailManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Contre-audit B3 (commit ca88931) — deux 500 anonymes reproduites :
 *
 * 1. Adresse acceptée par `Assert\Email` mais refusée par Symfony Mime
 *    (`.a@x`, `a..b@x`, `a.@x`) : `RfcComplianceException` à l'envoi, APRÈS
 *    l'écriture en base → 500, compte orphelin, puis 500 à chaque « mot de
 *    passe oublié ». Attendu : 422 et rien d'écrit.
 * 2. Mot de passe sous 4096 caractères mais au-delà de 4096 OCTETS (le
 *    plafond du hacheur) → 500 « Invalid password. ». Attendu : 422.
 */
final class PublicInputHardeningTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function undeliverableLocalParts(): iterable
    {
        yield 'point en tête' => ['.lead%s'];
        yield 'deux points consécutifs' => ['a..b%s'];
        yield 'point final' => ['tr%s.'];
        // Contre-audit de dc6b849 : `new Address()` lève InvalidArgumentException
        // (et non RfcComplianceException) sur un caractère de contrôle -> 500.
        yield 'caractère de contrôle 0x01' => ["a\u{1}b%s"];
        yield 'caractère DEL 0x7F' => ["a\u{7f}b%s"];
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function undeliverableEmailOnEveryPublicWrite(): iterable
    {
        $routes = [
            'inscription client' => ['/api/client_registrations', '`user`'],
            'inscription freelance' => ['/api/collaborator_registrations', '`user`'],
            'newsletter' => ['/api/newsletter_subscribers', 'newsletter_subscriber'],
            'contact' => ['/api/contact_messages', 'contact_message'],
            'devis' => ['/api/quote_requests', 'quote_request'],
        ];
        foreach ($routes as $label => [$uri, $table]) {
            foreach (self::undeliverableLocalParts() as $case => [$local]) {
                yield "$label : $case" => [$uri, $table, $local];
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('undeliverableEmailOnEveryPublicWrite')]
    public function testUndeliverableEmailIsA422AndNothingIsWritten(string $uri, string $table, string $local): void
    {
        $email = sprintf($local, bin2hex(random_bytes(4))).'@example.test';
        $client = $this->post($uri, $this->payloadFor($uri, $email));

        self::assertResponseStatusCodeSame(422, "Adresse non distribuable sur $uri : 422 attendu, jamais 500 ni 201. ".$client->getResponse()->getContent());
        $fields = array_map(static fn (array $v): string => $v['propertyPath'] ?? '', json_decode((string) $client->getResponse()->getContent(), true)['violations'] ?? []);
        self::assertContains('email', $fields);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame(0, (int) $em->getConnection()->fetchOne("SELECT COUNT(*) FROM $table WHERE email = ?", [$email]), 'Rien ne doit être écrit en base.');
    }

    public function testSendNowWithAnAlreadyStoredUndeliverableAddressReturnsFalseInsteadOfThrowing(): void
    {
        // Défense en profondeur : adresses stockées AVANT ce correctif
        // (« mot de passe oublié », renvoi de vérification).
        self::bootKernel();
        /** @var EmailManager $manager */
        $manager = static::getContainer()->get(EmailManager::class);

        self::assertFalse($manager->sendNow('.lead@example.test', 'Sujet', 'confirmation_email', ['fullName' => 'X', 'verifyUrl' => 'https://example.test']));
        self::assertFalse($manager->sendNow("a\u{1}b@example.test", 'Sujet', 'confirmation_email', ['fullName' => 'X', 'verifyUrl' => 'https://example.test']));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function signupRoutes(): iterable
    {
        yield 'client' => ['/api/client_registrations'];
        yield 'freelance' => ['/api/collaborator_registrations'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('signupRoutes')]
    public function testMultibytePasswordOverTheHasherByteLimitIsA422NeverA500(string $uri): void
    {
        // 1028 caractères mais 4100 octets : passait Length(max: 4096) en
        // caractères, puis le hacheur levait « Invalid password. » (500).
        $payload = $this->payloadFor($uri, 'mb-'.bin2hex(random_bytes(4)).'@example.test');
        $payload['plainPassword'] = 'Aa1!'.str_repeat('😀', 1024);
        $client = $this->post($uri, $payload);

        self::assertResponseStatusCodeSame(422, (string) $client->getResponse()->getContent());
        $fields = array_map(static fn (array $v): string => $v['propertyPath'] ?? '', json_decode((string) $client->getResponse()->getContent(), true)['violations'] ?? []);
        self::assertContains('plainPassword', $fields);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('signupRoutes')]
    public function testMultibytePasswordUnderTheByteLimitIsStillAccepted(string $uri): void
    {
        $email = 'mbok-'.bin2hex(random_bytes(4)).'@example.test';
        $payload = $this->payloadFor($uri, $email);
        $payload['plainPassword'] = 'Aa1!'.str_repeat('😀', 1023); // 4096 octets pile
        $this->post($uri, $payload);

        self::assertResponseStatusCodeSame(201);
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement('DELETE FROM `user` WHERE email = ?', [$email]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function malformedSpecialties(): iterable
    {
        yield 'objet JSON' => [['x' => 'Backend', 'y' => 'Design']];
        yield 'spécialité blanche' => [[' ']];
        yield 'élément tableau' => [[[]]];
        yield 'élément entier' => [[1]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('malformedSpecialties')]
    public function testMalformedSpecialtiesAreA422(mixed $specialties): void
    {
        $uri = '/api/collaborator_registrations';
        $payload = $this->payloadFor($uri, 'sp-'.bin2hex(random_bytes(4)).'@example.test');
        $payload['specialties'] = $specialties;
        $client = $this->post($uri, $payload);

        self::assertResponseStatusCodeSame(422, (string) $client->getResponse()->getContent());
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(string $uri, string $email): array
    {
        return match ($uri) {
            '/api/client_registrations', '/api/collaborator_registrations' => ['email' => $email, 'fullName' => 'Nom Prénom', 'plainPassword' => 'Motdepasse1!', 'agreeTerms' => true],
            '/api/newsletter_subscribers' => ['email' => $email, 'locale' => 'fr'],
            '/api/contact_messages' => ['source' => 'contact', 'name' => 'Nom', 'email' => $email, 'subject' => 'Sujet', 'message' => 'Bonjour, ceci est un message.', 'channel' => 'email'],
            '/api/quote_requests' => ['name' => 'Nom', 'email' => $email, 'category' => 'Web', 'channel' => 'email', 'message' => 'Un projet de site.'],
            default => throw new \LogicException("Route sans charge utile de test : $uri"),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(string $uri, array $payload): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $client->request('POST', $uri, server: [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
            'REMOTE_ADDR' => sprintf('2001:db8:%x::7', random_int(1, 0xFFFF)),
        ], content: (string) json_encode($payload));

        return $client;
    }
}
