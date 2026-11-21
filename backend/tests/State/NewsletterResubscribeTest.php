<?php

namespace App\Tests\State;

use App\Entity\NewsletterSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Ré-inscription newsletter (audit 2026-11-20, P2 RGPD) : n'importe qui peut
 * saisir l'adresse d'un tiers désinscrit — elle doit repasser par un double
 * opt-in, jamais redevenir destinataire directement.
 */
final class NewsletterResubscribeTest extends WebTestCase
{
    public function testResubscribingAnUnsubscribedAddressRequiresANewConfirmation(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $email = 'resub-'.bin2hex(random_bytes(4)).'@example.test';
        $subscriber = (new NewsletterSubscriber())->setEmail($email);
        $subscriber->confirm();
        $subscriber->unsubscribe();
        $em->persist($subscriber);
        $em->flush();
        $id = $subscriber->getId();

        try {
            $client->request('POST', '/api/newsletter_subscribers', server: [
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json',
                'REMOTE_ADDR' => sprintf('2001:db8:%x::1', random_int(1, 0xFFFF)),
            ], content: (string) json_encode(['email' => strtoupper($email), 'locale' => 'fr']));

            self::assertResponseStatusCodeSame(201);
            self::assertEmailCount(1);
            $message = self::getMailerMessage();
            self::assertNotNull($message);
            self::assertEmailAddressContains($message, 'To', $email);

            $em->clear();
            $reloaded = $em->find(NewsletterSubscriber::class, $id);
            self::assertNotNull($reloaded);
            self::assertNull($reloaded->getUnsubscribedAt());
            self::assertFalse($reloaded->isConfirmed(), 'Plus confirmée tant que le nouveau lien n\'est pas cliqué.');
        } finally {
            $em->clear();
            $stale = $em->find(NewsletterSubscriber::class, $id);
            if (null !== $stale) {
                $em->remove($stale);
                $em->flush();
            }
        }
    }
}
