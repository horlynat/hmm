<?php

namespace App\Tests\Templates;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use App\Tests\Functional\FunctionalTestCase;
use Symfony\Component\Finder\Finder;

/**
 * XSS stocké back-office (audit 2026-11-20, P1 #5) : les confirmations
 * `onsubmit="return confirm('… {{ user.email }} …')"` exécutaient du JS
 * fourni par l'utilisateur — le navigateur décode `&#039;` en `'` AVANT
 * d'évaluer l'attribut, l'échappement HTML de Twig ne protégeait donc rien.
 *
 * Deux garde-fous :
 * 1. statique : aucun gestionnaire d'évènement inline (`on…=`) d'aucun
 *    template ne contient d'interpolation Twig (`{{`), même sur plusieurs
 *    lignes ;
 * 2. dynamique (attaque réelle) : un compte dont le nom ET l'email portent
 *    `');alert(1);//` est affiché dans les fiches back-office qui
 *    l'interpolaient ; le HTML rendu ne doit contenir la charge dans AUCUN
 *    attribut `on…` ni dans aucun <script>, et doit la restituer intacte
 *    (en simple donnée) dans `data-confirm-message-value`.
 */
final class InlineJsInjectionTest extends FunctionalTestCase
{
    private const PAYLOAD = "');alert(1);//";

    public function testNoTemplateInterpolatesTwigInsideInlineEventHandlers(): void
    {
        $offenders = [];
        $finder = (new Finder())->files()->in(\dirname(__DIR__, 2).'/templates')->name('*.twig');

        foreach ($finder as $file) {
            $source = $file->getContents();
            // Attribut on…="…" (guillemets doubles ou simples), multi-ligne.
            if (preg_match_all('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\')/i', $source, $matches)) {
                foreach ($matches[1] as $value) {
                    if (str_contains($value, '{{') || str_contains($value, '{%')) {
                        $offenders[] = $file->getRelativePathname().': '.trim(mb_substr($value, 0, 120));
                    }
                }
            }
        }

        self::assertSame([], $offenders, "Gestionnaires d'évènements inline construits avec une donnée Twig : passer par data-* + contrôleur Stimulus.");
    }

    public function testMaliciousNameAndEmailNeverReachExecutableJs(): void
    {
        $client = $this->client;
        $em = $this->em();

        $suffix = bin2hex(random_bytes(4));
        // Vrai parcours B2 : super-admin avec 2FA (obligatoire pour le
        // back-office), élévation demandée par mot de passe DANS cette
        // session (elle n'est jamais déduite de la seule date en base).
        $admin = $this->createUser(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], twoFactor: true, email: "sa-{$suffix}@example.test");

        $victim = (new User())
            ->setEmail("x".self::PAYLOAD."{$suffix}@example.test")
            ->setFullName('Mallory'.self::PAYLOAD)
            ->setRoles(['ROLE_USER']);
        $victim->setPassword('!');

        $victimAdmin = (new User())
            ->setEmail("y".self::PAYLOAD."{$suffix}@example.test")
            ->setFullName('Eve'.self::PAYLOAD)
            ->setRoles(['ROLE_ADMIN']);
        $victimAdmin->setPassword('!');

        $em->persist($victim);
        $em->persist($victimAdmin);
        $em->flush();

        try {
            $client->loginUser($admin, 'main');
            $crawler = $client->request('GET', '/admin/profil/'.$admin->getId());
            self::assertResponseIsSuccessful();
            $client->submit($crawler->filter('form[action$="/profile/elevation/activate"]')->form(['password' => self::PASSWORD]));
            self::assertResponseRedirects();

            $pages = [
                '/admin/users/'.$victim->getId(),
                '/admin/admins/'.$victimAdmin->getId(),
            ];

            foreach ($pages as $url) {
                $client->request('GET', $url);
                self::assertResponseIsSuccessful("$url doit s'afficher pour un super-admin.");
                $this->assertPayloadIsInert($client, $url);
            }
        } finally {
            foreach ([$victim, $victimAdmin, $admin] as $user) {
                $managed = $em->find(User::class, $user->getId());
                if (null !== $managed) {
                    $em->remove($managed);
                }
            }
            $em->flush();
        }
    }

    private function assertPayloadIsInert(KernelBrowser $client, string $url): void
    {
        $html = (string) $client->getResponse()->getContent();
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        // Aucun attribut on… ne contient la charge (valeur DÉCODÉE, comme le navigateur la verrait).
        foreach ($xpath->query('//@*[starts-with(name(), "on")]') ?: [] as $attr) {
            self::assertStringNotContainsString(self::PAYLOAD, (string) $attr->nodeValue, "$url : charge dans un gestionnaire inline {$attr->nodeName}.");
            self::assertStringNotContainsString('alert(', (string) $attr->nodeValue, "$url : charge dans un gestionnaire inline {$attr->nodeName}.");
        }

        // Aucun attribut de type URL javascript:, ni x-on/@ Alpine contenant la charge.
        foreach ($xpath->query('//@*') ?: [] as $attr) {
            $name = strtolower($attr->nodeName);
            if (str_starts_with($name, 'x-') || str_starts_with($name, '@') || str_starts_with($name, ':')) {
                self::assertStringNotContainsString(self::PAYLOAD, (string) $attr->nodeValue, "$url : charge dans une expression Alpine {$name}.");
            }
        }

        foreach ($xpath->query('//script') ?: [] as $script) {
            self::assertStringNotContainsString('alert(1)', (string) $script->textContent, "$url : charge dans un <script>.");
        }

        // La confirmation existe bien, et porte la donnée telle quelle (non exécutée).
        $confirms = $xpath->query('//form[@data-controller="confirm"]/@data-confirm-message-value');
        self::assertNotFalse($confirms);
        $withPayload = array_filter(
            iterator_to_array($confirms),
            static fn (\DOMNode $n): bool => str_contains((string) $n->nodeValue, self::PAYLOAD),
        );
        self::assertNotEmpty($withPayload, "$url : la confirmation de déconnexion doit afficher l'email tel quel.");
    }
}
