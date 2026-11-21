<?php

namespace App\Tests\Entity;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Champs publics sans `Assert\Length` (audit 2026-11-20, P2) : un visiteur
 * anonyme pouvait soumettre une valeur plus longue que la colonne — Doctrine
 * échoue à l'INSERT (`Data too long for column`), ce qui remontait en 500 +
 * déclenchait les emails d'alerte d'erreur (cf. ErrorNotifier), pour une
 * entrée pourtant entièrement illégitime. Attaque : POST avec chaque champ
 * texte rempli au-delà de sa colonne → doit être un 422 propre (violation de
 * validation), jamais un 500.
 */
final class PublicEntityLengthGuardTest extends WebTestCase
{
    public function testOversizedContactMessageFieldsAreRejectedWith422NotA500(): void
    {
        $payload = [
            'source' => str_repeat('a', 101),       // colonne 100
            'name' => 'Nom',
            'company' => str_repeat('a', 256),      // colonne 255
            'email' => 'oversize-'.bin2hex(random_bytes(4)).'@example.test',
            'channel' => str_repeat('a', 31),        // colonne 30
            'slot' => str_repeat('a', 101),          // colonne 100
            'subject' => 'Sujet',
            'message' => str_repeat('a', 5001),      // plafond applicatif 5000
        ];

        $client = static::createClient();
        $client->request('POST', '/api/contact_messages', server: [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
            'REMOTE_ADDR' => sprintf('2001:db8:%x::1', random_int(1, 0xFFFF)),
        ], content: (string) json_encode($payload));

        self::assertResponseStatusCodeSame(422, 'Un champ trop long doit être une violation de validation, jamais un 500.');
        $body = json_decode((string) $client->getResponse()->getContent(), true) ?? [];
        $violationFields = array_map(static fn (array $v): string => $v['propertyPath'] ?? '', $body['violations'] ?? []);
        foreach (['source', 'company', 'channel', 'slot', 'message'] as $field) {
            self::assertContains($field, $violationFields, "Le champ \"$field\" devrait être signalé trop long.");
        }
    }

    public function testOversizedQuoteRequestFieldsAreRejectedWith422NotA500(): void
    {
        $payload = [
            'name' => 'Nom',
            'email' => 'oversize-'.bin2hex(random_bytes(4)).'@example.test',
            'category' => str_repeat('a', 101),         // colonne 100
            'categoryDetail' => str_repeat('a', 256),    // colonne 255
            'source' => str_repeat('a', 101),            // colonne 100
            'budget' => str_repeat('a', 151),            // colonne 150
            'currency' => str_repeat('a', 11),           // colonne 10
            'timeline' => str_repeat('a', 101),          // colonne 100
            'channel' => str_repeat('a', 31),            // colonne 30
            'attachmentName' => str_repeat('a', 256),    // colonne 255
            'message' => str_repeat('a', 5001),          // plafond applicatif 5000
            'clarifications' => array_fill(0, 11, ['question' => 'Q ?', 'answer' => 'R.']), // plafond 10
        ];

        $client = static::createClient();
        $client->request('POST', '/api/quote_requests', server: [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
            'REMOTE_ADDR' => sprintf('2001:db8:%x::1', random_int(1, 0xFFFF)),
        ], content: (string) json_encode($payload));

        self::assertResponseStatusCodeSame(422);
        $body = json_decode((string) $client->getResponse()->getContent(), true) ?? [];
        $violationFields = array_map(static fn (array $v): string => $v['propertyPath'] ?? '', $body['violations'] ?? []);
        foreach (['category', 'categoryDetail', 'source', 'budget', 'currency', 'timeline', 'channel', 'attachmentName', 'message', 'clarifications'] as $field) {
            self::assertContains($field, $violationFields, "Le champ \"$field\" devrait être signalé.");
        }
    }

    public function testQuoteRequestClarificationsNestedFieldsAreBounded(): void
    {
        $payload = [
            'name' => 'Nom',
            'email' => 'oversize-'.bin2hex(random_bytes(4)).'@example.test',
            'category' => 'web',
            'channel' => 'email',
            'message' => 'Un message suffisamment long pour être valide.',
            'clarifications' => [['question' => str_repeat('q', 501), 'answer' => str_repeat('a', 2001)]],
        ];

        $client = static::createClient();
        $client->request('POST', '/api/quote_requests', server: [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
            'REMOTE_ADDR' => sprintf('2001:db8:%x::2', random_int(1, 0xFFFF)),
        ], content: (string) json_encode($payload));

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * Contre-audit B3 : la regex du téléphone laissait passer `+` suivi de
     * 20 chiffres (21 caractères, colonne de 20) -> 500 anonyme ; `\s` et `$`
     * sans `D` acceptaient aussi tabulation et saut de ligne final.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function invalidPhones(): iterable
    {
        foreach (['/api/contact_messages' => 'contact', '/api/quote_requests' => 'devis'] as $uri => $label) {
            yield "$label : 21 caractères" => [$uri, '+'.str_repeat('1', 20)];
            yield "$label : saut de ligne final" => [$uri, str_repeat('1', 10)."\n"];
            yield "$label : tabulation" => [$uri, "0612\t345678"];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidPhones')]
    public function testInvalidPhoneIsA422NeverA500(string $uri, string $phone): void
    {
        $payload = '/api/contact_messages' === $uri
            ? ['source' => 'contact', 'name' => 'Nom', 'email' => 'p-'.bin2hex(random_bytes(4)).'@example.test', 'subject' => 'Sujet', 'message' => 'Bonjour, ceci est un message.', 'channel' => 'whatsapp', 'phone' => $phone]
            : ['name' => 'Nom', 'email' => 'p-'.bin2hex(random_bytes(4)).'@example.test', 'category' => 'Web', 'channel' => 'whatsapp', 'message' => 'Un projet de site.', 'phone' => $phone];

        $client = static::createClient();
        $client->request('POST', $uri, server: [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
            'REMOTE_ADDR' => sprintf('2001:db8:%x::1', random_int(1, 0xFFFF)),
        ], content: (string) json_encode($payload));

        self::assertResponseStatusCodeSame(422, "Téléphone invalide sur $uri : 422 attendu, jamais 500 ni 201.");
        $fields = array_map(static fn (array $v): string => $v['propertyPath'] ?? '', json_decode((string) $client->getResponse()->getContent(), true)['violations'] ?? []);
        self::assertContains('phone', $fields);
    }

    public function testValidPhoneIsStillAccepted(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/contact_messages', server: [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
            'REMOTE_ADDR' => sprintf('2001:db8:%x::1', random_int(1, 0xFFFF)),
        ], content: (string) json_encode(['source' => 'contact', 'name' => 'Nom', 'email' => 'ok-'.bin2hex(random_bytes(4)).'@example.test', 'subject' => 'Sujet', 'message' => 'Bonjour, ceci est un message.', 'channel' => 'whatsapp', 'phone' => '+33 6 12 34 56 78']));

        self::assertResponseStatusCodeSame(201, (string) $client->getResponse()->getContent());
    }

    public function testValidContactMessageIsStillAccepted(): void
    {
        // Non-régression : les plafonds ajoutés ne doivent pas bloquer un envoi légitime.
        $email = 'alice-'.bin2hex(random_bytes(4)).'@example.test';

        $client = static::createClient();
        $client->request('POST', '/api/contact_messages', server: [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
            'REMOTE_ADDR' => sprintf('2001:db8:%x::3', random_int(1, 0xFFFF)),
        ], content: (string) json_encode([
            'source' => 'Rendez-vous',
            'name' => 'Alice Martin',
            'email' => $email,
            'channel' => 'Email',
            'subject' => 'Demande de contact',
            'message' => 'Un message de taille tout à fait normale et légitime.',
        ]));

        self::assertResponseStatusCodeSame(201);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        // `id` n'est pas exposé en sortie publique (groupe api_admin uniquement) —
        // on vérifie la persistance réelle par l'email plutôt que par la réponse JSON.
        $id = $em->getConnection()->fetchOne('SELECT id FROM contact_message WHERE email = ?', [$email]);
        self::assertNotFalse($id, 'Le message valide doit avoir été persisté.');
        $em->getConnection()->executeStatement('DELETE FROM contact_message WHERE id = ?', [$id]);
    }

    /**
     * Contre-audit B3 : à la limite exacte de la colonne (20 caractères), le
     * téléphone doit passer jusqu'à la base — preuve que la borne est la bonne.
     */
    public function testPhoneAtExactColumnLengthIsPersisted(): void
    {
        $email = 'edge-'.bin2hex(random_bytes(4)).'@example.test';
        $client = static::createClient();
        $client->request('POST', '/api/contact_messages', server: [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
            'REMOTE_ADDR' => sprintf('2001:db8:%x::4', random_int(1, 0xFFFF)),
        ], content: (string) json_encode(['source' => 'contact', 'name' => 'Nom', 'email' => $email, 'subject' => 'Sujet', 'message' => 'Bonjour, ceci est un message.', 'channel' => 'whatsapp', 'phone' => '+'.str_repeat('1', 19)]));

        self::assertResponseStatusCodeSame(201, (string) $client->getResponse()->getContent());
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('+'.str_repeat('1', 19), $em->getConnection()->fetchOne('SELECT phone FROM contact_message WHERE email = ?', [$email]));
        $em->getConnection()->executeStatement('DELETE FROM contact_message WHERE email = ?', [$email]);
    }

    /**
     * Contre-audit B3 : l'inscription publique (client et freelance) écrivait
     * dans des colonnes `User` sans borne applicative — `email` (180),
     * `availability` (100), `portfolioUrl` (255) donnaient une 500 anonyme ;
     * `bio` et `specialties` n'avaient aucune limite.
     *
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function oversizedSignupFields(): iterable
    {
        $longEmail = static fn (): string => str_repeat('a', 64).'@'.str_repeat('b', 60).'.'.str_repeat('c', 60).'.example.test';
        foreach (['/api/client_registrations' => 'client', '/api/collaborator_registrations' => 'freelance'] as $uri => $label) {
            yield "$label : e-mail de plus de 180 caractères" => [$uri, ['email' => $longEmail()], 'email'];
            yield "$label : téléphone de 21 caractères" => [$uri, ['phone' => '+'.str_repeat('1', 20)], 'phone'];
        }
        $freelance = '/api/collaborator_registrations';
        yield 'freelance : disponibilité de 101 caractères' => [$freelance, ['availability' => str_repeat('a', 101)], 'availability'];
        yield 'freelance : portfolio de plus de 255 caractères' => [$freelance, ['portfolioUrl' => 'https://example.com/'.str_repeat('a', 300)], 'portfolioUrl'];
        yield 'freelance : présentation de 2001 caractères' => [$freelance, ['bio' => str_repeat('a', 2001)], 'bio'];
        yield 'freelance : spécialité imbriquée' => [$freelance, ['specialties' => [['x' => 'y']]], 'specialties[0]'];
        yield 'freelance : trop de spécialités' => [$freelance, ['specialties' => array_map(static fn (int $i): string => "S$i", range(1, 11))], 'specialties'];
        yield 'freelance : spécialité de 51 caractères' => [$freelance, ['specialties' => [str_repeat('a', 51)]], 'specialties[0]'];
    }

    /**
     * @param array<string, mixed> $override
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('oversizedSignupFields')]
    public function testOversizedSignupFieldIsA422NeverA500(string $uri, array $override, string $field): void
    {
        $payload = array_merge($this->signupPayload(), $override);

        $client = static::createClient();
        $client->request('POST', $uri, server: [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
            'REMOTE_ADDR' => sprintf('2001:db8:%x::5', random_int(1, 0xFFFF)),
        ], content: (string) json_encode($payload));

        self::assertResponseStatusCodeSame(422, "Champ « $field » trop long sur $uri : 422 attendu, jamais 500 ni 201. ".$client->getResponse()->getContent());
        $fields = array_map(static fn (array $v): string => $v['propertyPath'] ?? '', json_decode((string) $client->getResponse()->getContent(), true)['violations'] ?? []);
        self::assertContains($field, $fields);
    }

    public function testValidFreelanceSignupAtLimitsIsStillAccepted(): void
    {
        $payload = array_merge($this->signupPayload(), [
            'availability' => str_repeat('a', 100),
            'portfolioUrl' => 'https://example.com/'.str_repeat('a', 235),
            'bio' => str_repeat('é', 2000),
            'specialties' => ['Backend', 'AI / Data', 'Cybersecurity'],
            'phone' => '+33 6 12 34 56 78',
        ]);

        $client = static::createClient();
        $client->request('POST', '/api/collaborator_registrations', server: [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
            'REMOTE_ADDR' => sprintf('2001:db8:%x::6', random_int(1, 0xFFFF)),
        ], content: (string) json_encode($payload));

        self::assertResponseStatusCodeSame(201, (string) $client->getResponse()->getContent());
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement('DELETE FROM `user` WHERE email = ?', [$payload['email']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function signupPayload(): array
    {
        return [
            'email' => 'signup-'.bin2hex(random_bytes(4)).'@example.test',
            'fullName' => 'Nom Prénom',
            'plainPassword' => 'Motdepasse1!',
            'agreeTerms' => true,
        ];
    }
}
