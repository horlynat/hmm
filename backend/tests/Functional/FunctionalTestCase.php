<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Message\SendEmail;
use App\Service\SecretEncryptor;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base des tests fonctionnels (requêtes HTTP réelles à travers tout le
 * pipeline : firewalls, access_control, listeners, contrôleurs).
 *
 * Pas de rollback transactionnel (aucun bundle dédié installé) : chaque test
 * crée ses propres comptes avec un e-mail aléatoire, et une IP cliente
 * aléatoire (REMOTE_ADDR) pour ne jamais partager un compteur de rate-limiter
 * avec un autre test, ni entre deux exécutions de la suite.
 */
abstract class FunctionalTestCase extends WebTestCase
{
    protected const PASSWORD = 'Sup3r-Secret!pw';

    protected KernelBrowser $client;
    protected string $clientIp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        // Le noyau est conservé entre deux requêtes d'un même test : sans ça,
        // l'EntityManager (et donc les entités créées ici) change à chaque
        // requête et les vérifications "après coup" lisent un état périmé.
        $this->client->disableReboot();
        $this->clientIp = sprintf('10.%d.%d.%d', random_int(0, 255), random_int(0, 255), random_int(1, 254));
        $this->client->setServerParameter('REMOTE_ADDR', $this->clientIp);
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @param list<string> $roles
     */
    protected function createUser(array $roles = ['ROLE_USER'], bool $twoFactor = false, ?string $email = null, bool $systemAccount = false): User
    {
        $user = new User();
        $user->setEmail($email ?? sprintf('u-%s@test.invalid', bin2hex(random_bytes(6))));
        $user->setFullName('Test User');
        $user->setRoles($roles);
        $user->setIsVerified(true);
        $user->setIsActive(true);
        $user->setIsSystemAccount($systemAccount);
        $user->setCreatedAt(new \DateTimeImmutable());

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));

        if ($twoFactor) {
            $this->enableTwoFactor($user);
        }

        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    /**
     * Active une vraie 2FA TOTP (secret chiffré comme en prod). Le secret en
     * clair est mémorisé sur l'instance pour totpCodeFor().
     */
    protected function enableTwoFactor(User $user): void
    {
        /** @var TotpAuthenticatorInterface $totp */
        $totp = static::getContainer()->get(TotpAuthenticatorInterface::class);
        $secret = $totp->generateSecret();
        $this->plainTotpSecrets[$user->getEmail()] = $secret;

        /** @var SecretEncryptor $encryptor */
        $encryptor = static::getContainer()->get(SecretEncryptor::class);
        $user->setTotpSecret($encryptor->encrypt($secret));
        $user->setIsTwoFactorEnabled(true);
    }

    /** @var array<string, string> */
    private array $plainTotpSecrets = [];

    protected function totpCodeFor(User $user, int $offsetSeconds = 0): string
    {
        $secret = $this->plainTotpSecrets[$user->getEmail()] ?? throw new \LogicException('2FA non activée via enableTwoFactor().');

        return self::computeTotp($secret, time() + $offsetSeconds);
    }

    /** TOTP RFC 6238 (SHA1, 30 s, 6 chiffres) — indépendant de l'implémentation testée. */
    protected static function computeTotp(string $base32Secret, int $timestamp): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split(strtoupper(rtrim($base32Secret, '='))) as $char) {
            $bits .= str_pad(decbin((int) strpos($alphabet, $char)), 5, '0', \STR_PAD_LEFT);
        }
        $key = '';
        foreach (str_split($bits, 8) as $byte) {
            if (8 === \strlen($byte)) {
                $key .= \chr((int) bindec($byte));
            }
        }
        $counter = pack('N*', 0).pack('N*', intdiv($timestamp, 30));
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = \ord($hash[19]) & 0xF;
        $value = ((\ord($hash[$offset]) & 0x7F) << 24) | ((\ord($hash[$offset + 1]) & 0xFF) << 16) | ((\ord($hash[$offset + 2]) & 0xFF) << 8) | (\ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % 1000000), 6, '0', \STR_PAD_LEFT);
    }

    protected function jwtFor(User $user): string
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string>     $server
     */
    protected function jsonRequest(string $method, string $uri, ?array $body = null, ?string $jwt = null, array $server = []): Response
    {
        $server = array_merge([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $server);
        if (null !== $jwt) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$jwt;
        }

        $this->client->request($method, $uri, [], [], $server, null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR));

        return $this->client->getResponse();
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(Response $response): array
    {
        $data = json_decode((string) $response->getContent(), true);

        return \is_array($data) ? $data : [];
    }

    protected function reload(User $user): User
    {
        $this->em()->clear();
        $fresh = $this->em()->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $fresh);

        return $fresh;
    }

    /**
     * Tous les e-mails produits par la requête : envois synchrones (mailer)
     * ET envois asynchrones (messages SendEmail restés dans le transport
     * en mémoire), rendus sous forme de texte (sujet + destinataires + contexte).
     *
     * @return list<string>
     */
    protected function capturedEmails(): array
    {
        $out = [];
        foreach (self::getMailerMessages() as $message) {
            if ($message instanceof TemplatedEmail) {
                $out[] = implode(' ', array_map(static fn ($a) => $a->getAddress(), $message->getTo()))
                    .' | '.$message->getSubject().' | '.json_encode($message->getContext(), \JSON_UNESCAPED_SLASHES | \JSON_PARTIAL_OUTPUT_ON_ERROR)
                    .' | '.html_entity_decode((string) $message->getHtmlBody());
            }
        }

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof SendEmail) {
                $out[] = $message->to.' | '.$message->subject.' | '.json_encode($message->context, \JSON_UNESCAPED_SLASHES | \JSON_PARTIAL_OUTPUT_ON_ERROR);
            }
        }

        return $out;
    }
}
