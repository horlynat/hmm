<?php

namespace App\Tests\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * AdminCurrencyController::set() (audit 2026-11-20, Info) : CSRF + open
 * redirect chaîné via le Referer réinjecté tel quel.
 */
final class AdminCurrencyControllerTest extends WebTestCase
{
    private function admin(EntityManagerInterface $em): User
    {
        $user = (new User())
            ->setEmail('currency-'.bin2hex(random_bytes(4)).'@example.test')
            ->setRoles(['ROLE_ADMIN'])
            ->setIsVerified(true);
        $user->setPassword('!');
        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function testRequestWithoutCsrfTokenDoesNotSetTheCookie(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->admin($em);

        try {
            $client->loginUser($user, 'main');
            $client->request('POST', '/admin/currency/set', ['currency' => 'USD'], server: [
                'HTTP_REFERER' => 'https://dark.horlynat.com/admin/dashboard',
            ]);

            self::assertResponseRedirects();
            self::assertNull($client->getResponse()->headers->getCookies()[0] ?? null, 'Sans jeton CSRF valide, aucun cookie ne doit être posé.');
        } finally {
            $em->remove($em->find(User::class, $user->getId()));
            $em->flush();
        }
    }

    public function testForeignRefererIsNeverFollowed(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->admin($em);

        try {
            $client->loginUser($user, 'main');
            $crawler = $client->request('GET', '/admin/dashboard/');
            $token = (string) $crawler->filter('form[action$="/admin/currency/set"] input[name="_token"]')->attr('value');
            self::assertNotSame('', $token);

            // Un attaquant forge le Referer : tente de rediriger l'admin vers un site tiers (phishing).
            $client->request('POST', '/admin/currency/set', [
                '_token' => $token,
                'currency' => 'USD',
            ], server: [
                'HTTP_REFERER' => 'https://evil.example/phishing',
            ]);

            self::assertResponseRedirects();
            $location = $client->getResponse()->headers->get('Location');
            self::assertNotNull($location);
            self::assertStringNotContainsString('evil.example', $location, 'Le Referer forgé ne doit jamais devenir la cible de redirection.');
        } finally {
            $em->remove($em->find(User::class, $user->getId()));
            $em->flush();
        }
    }

    public function testValidRequestWithSameHostRefererSetsCookieAndRedirectsBack(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->admin($em);

        try {
            $client->loginUser($user, 'main');
            $crawler = $client->request('GET', '/admin/dashboard/');
            $token = (string) $crawler->filter('form[action$="/admin/currency/set"] input[name="_token"]')->attr('value');

            $client->request('POST', '/admin/currency/set', [
                '_token' => $token,
                'currency' => 'USD',
            ], server: [
                'HTTP_REFERER' => 'http://localhost/admin/dashboard',
            ]);

            self::assertResponseRedirects('http://localhost/admin/dashboard');
            $cookies = $client->getResponse()->headers->getCookies();
            self::assertNotEmpty($cookies);
            self::assertSame('display_currency', $cookies[0]->getName());
            self::assertSame('USD', $cookies[0]->getValue());
        } finally {
            $em->remove($em->find(User::class, $user->getId()));
            $em->flush();
        }
    }
}
