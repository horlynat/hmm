<?php

namespace App\Tests\Service;

use App\Service\MediaUploader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Un SVG uploadé est servi tel quel depuis /uploads/ : ce qui survit au
 * nettoyage s'exécute à l'ouverture directe de son URL. Ces cas verrouillent
 * le comportement de enshrined/svg-sanitize (montée 0.22 → 1.x, cf.
 * CVE-2026-107380 : XSS via entité DTD) au niveau où l'application l'utilise.
 *
 * Chaque cas déclare son issue attendue : un changement de comportement de la
 * bibliothèque (refus ↔ neutralisation) doit être vu et relu, pas absorbé.
 */
final class MediaUploaderSvgTest extends TestCase
{
    private const NEUTRALISED = 'neutralised';
    private const REFUSED = 'refused';
    private const SANITIZER_REFUSAL = "Ce fichier SVG n'a pas pu être sécurisé et a été refusé.";

    private string $dir;
    private MediaUploader $uploader;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/hmm-svg-test-'.bin2hex(random_bytes(6));
        mkdir($this->dir);

        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static fn (object $m): Envelope => new Envelope($m));

        $this->uploader = new MediaUploader(
            $this->dir,
            new AsciiSlugger(),
            new NullLogger(),
            $bus,
            new RequestStack(),
            ['image/svg+xml'],
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    private function uploadSvg(string $svg): string
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'svg');
        file_put_contents($tmp, $svg);
        $file = new UploadedFile($tmp, 'logo.svg', 'image/svg+xml', null, true);

        try {
            $result = $this->uploader->upload($file);
        } finally {
            // MediaUploader ne supprime le tmp qu'en cas de succès.
            @unlink($tmp);
        }

        return (string) file_get_contents($this->dir.'/'.$result['filename']);
    }

    /**
     * @return iterable<string, array{0: string, 1: self::NEUTRALISED|self::REFUSED, 2: list<string>}>
     */
    public static function hostile(): iterable
    {
        $ns = 'xmlns="http://www.w3.org/2000/svg"';
        $xlink = 'xmlns:xlink="http://www.w3.org/1999/xlink"';

        // --- Exécution de script ---
        yield 'script element' => ["<svg $ns><script>alert(1)</script><circle r=\"4\"/></svg>", self::NEUTRALISED, ['<script', 'alert(1)']];
        yield 'SCRIPT uppercase' => ["<svg $ns><SCRIPT>alert(1)</SCRIPT><circle r=\"4\"/></svg>", self::NEUTRALISED, ['script', 'alert(1)']];
        yield 'script in CDATA' => ["<svg $ns><script><![CDATA[alert(1)]]></script></svg>", self::NEUTRALISED, ['script', 'alert(1)']];
        yield 'XHTML-namespaced script' => ["<svg $ns><h:script xmlns:h=\"http://www.w3.org/1999/xhtml\">alert(1)</h:script></svg>", self::NEUTRALISED, ['script', 'alert(1)']];
        yield 'script without svg namespace' => ['<svg><script>alert(1)</script></svg>', self::NEUTRALISED, ['script', 'alert(1)']];
        yield 'event handler attributes' => ["<svg $ns onload=\"alert(1)\"><circle r=\"4\" onclick=\"alert(2)\"/></svg>", self::NEUTRALISED, ['onload', 'onclick', 'alert(']];
        yield 'javascript: href' => ["<svg $ns $xlink><a xlink:href=\"javascript:alert(1)\"><circle r=\"4\"/></a></svg>", self::NEUTRALISED, ['javascript:']];
        // Le navigateur retire tabulations / décode les entités dans une URL :
        // une aiguille « javascript: » littérale ne suffit pas.
        yield 'javascript: with tab (SVG2 href)' => ["<svg $ns><a href=\"java&#9;script:alert(1)\"><circle r=\"4\"/></a></svg>", self::NEUTRALISED, ['script:', 'alert(']];
        yield 'javascript: entity-encoded' => ["<svg $ns $xlink><a xlink:href=\"&#106;avascript:alert(1)\"><circle r=\"4\"/></a></svg>", self::NEUTRALISED, ['avascript', '&#106;', 'alert(']];
        // GHSA-q546 : href injecté par animation SMIL (corrigé en 1.1.0).
        yield '<animate> sets href' => ["<svg $ns $xlink><a href=\"#x\"><animate attributeName=\"href\" values=\"javascript:alert(1)\"/><text>x</text></a></svg>", self::NEUTRALISED, ['javascript:', '<animate']];
        yield '<set> sets href' => ["<svg $ns><a><set attributeName=\"href\" to=\"javascript:alert(1)\"/><circle r=\"4\"/></a></svg>", self::NEUTRALISED, ['javascript:', '<set']];
        yield 'foreignObject with HTML' => ["<svg $ns><foreignObject><body xmlns=\"http://www.w3.org/1999/xhtml\"><iframe src=\"javascript:alert(1)\"></iframe></body></foreignObject></svg>", self::NEUTRALISED, ['<iframe', 'javascript:', 'foreignObject']];
        yield '<use> of a data: SVG' => ["<svg $ns><use href=\"data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnPjxzY3JpcHQ+YWxlcnQoMSk8L3NjcmlwdD48L3N2Zz4=#x\"/></svg>", self::NEUTRALISED, ['data:']];
        yield 'PHP open tag' => ["<svg $ns><?php system('id'); ?><circle r=\"4\"/></svg>", self::NEUTRALISED, ['<?php', 'system(']];
        // Contre-audit rétroactif #147 : vecteurs bloqués par la bibliothèque
        // mais non verrouillés par un test (régression silencieuse possible).
        yield 'xml-stylesheet (XSLT)' => ["<?xml version=\"1.0\"?><?xml-stylesheet type=\"text/xsl\" href=\"https://evil.example/x.xsl\"?><svg $ns><circle r=\"4\"/></svg>", self::NEUTRALISED, ['xml-stylesheet', 'evil.example']];
        yield 'data:text/html in <a href>' => ["<svg $ns><a href=\"data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==\"><circle r=\"4\"/></a></svg>", self::NEUTRALISED, ['data:']];
        yield 'renamed xlink prefix' => ["<svg $ns xmlns:z=\"http://www.w3.org/1999/xlink\"><a z:href=\"javascript:alert(1)\"><circle r=\"4\"/></a></svg>", self::NEUTRALISED, ['javascript:', 'alert(']];
        yield 'CSS-escaped url() in <style>' => ["<svg $ns><style>rect{fill:u\\72l(https://evil.example/x)}</style><rect width=\"4\" height=\"4\"/></svg>", self::NEUTRALISED, ['evil.example']];

        // --- Références distantes (pixel espion / exfiltration / SSRF au rendu) ---
        yield 'remote <image>' => ["<svg $ns $xlink><image xlink:href=\"https://evil.example/p.png\"/></svg>", self::NEUTRALISED, ['evil.example']];
        yield 'remote url() in style attribute' => ["<svg $ns><rect style=\"fill:url(https://evil.example/x)\" width=\"4\" height=\"4\"/></svg>", self::NEUTRALISED, ['evil.example']];
        yield '@import in <style>' => ["<svg $ns><style>@import url(https://evil.example/a.css);</style><circle r=\"4\"/></svg>", self::NEUTRALISED, ['evil.example', '@import']];

        // --- DTD ---
        // svg-sanitize 1.x retire le DOCTYPE avant le parsing : une DTD sans
        // entité référencée est simplement neutralisée…
        // Attributs par défaut déclarés en DTD (vecteur d'injection, et de
        // plantage dans CVE-2026-107379). Témoin : `fill` est autorisé par la
        // liste blanche — s'il apparaît, c'est que la DTD a atteint le parseur.
        yield 'DTD default attributes' => [
            '<?xml version="1.0"?><!DOCTYPE svg [<!ATTLIST svg onload CDATA "alert(1)"><!ATTLIST circle fill CDATA "red">]>'
            ."<svg $ns><circle r=\"4\"/></svg>",
            self::NEUTRALISED, ['onload', 'ATTLIST', 'alert(', 'red'],
        ];
        // … et un document qui référence une entité devenue indéfinie est
        // refusé (issue la plus sûre).
        // CVE-2026-107380 : entité custom nommée comme une référence de
        // caractère HTML5 (&Tab; …) qui reconstituait du balisage actif.
        yield 'custom DTD entity (CVE-2026-107380)' => [
            '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY Tab "&#60;script&#62;alert(1)&#60;/script&#62;">]>'
            ."<svg $ns><text>&Tab;</text></svg>",
            self::REFUSED, [],
        ];
        // XXE : lecture de fichier serveur — l'impact le plus grave côté back.
        yield 'XXE external entity' => [
            '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]>'
            ."<svg $ns><text>&x;</text></svg>",
            self::REFUSED, [],
        ];
        yield 'billion laughs' => [
            '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY a "aaaaaaaaaa">'
            .'<!ENTITY b "&a;&a;&a;&a;&a;&a;&a;&a;&a;&a;"><!ENTITY c "&b;&b;&b;&b;&b;&b;&b;&b;&b;&b;">'
            .'<!ENTITY d "&c;&c;&c;&c;&c;&c;&c;&c;&c;&c;"><!ENTITY e "&d;&d;&d;&d;&d;&d;&d;&d;&d;&d;">'
            .'<!ENTITY f "&e;&e;&e;&e;&e;&e;&e;&e;&e;&e;"><!ENTITY g "&f;&f;&f;&f;&f;&f;&f;&f;&f;&f;">]>'
            ."<svg $ns><text>&g;</text></svg>",
            self::REFUSED, [],
        ];
        // GHSA-f7cw (raison de la 1.1.1) : un document tronqué doit donner un
        // refus propre, pas une ValueError / 500.
        yield 'truncated document' => ["<svg $ns><circle", self::REFUSED, []];
    }

    /**
     * @param self::NEUTRALISED|self::REFUSED $expected
     * @param list<string>                    $forbidden
     */
    #[DataProvider('hostile')]
    public function testHostileSvgIsNeutralisedOrRefused(string $svg, string $expected, array $forbidden): void
    {
        $start = microtime(true);
        try {
            $stored = $this->uploadSvg($svg);
            $outcome = self::NEUTRALISED;
        } catch (\RuntimeException $e) {
            // Seul le refus du nettoyeur compte : un refus au contrôle MIME
            // ou une erreur d'écriture ne prouverait rien sur le nettoyage.
            self::assertSame(self::SANITIZER_REFUSAL, $e->getMessage());
            self::assertSame([], glob($this->dir.'/*'), 'Un SVG refusé ne doit laisser aucun fichier.');
            $stored = '';
            $outcome = self::REFUSED;
        }

        self::assertSame($expected, $outcome);
        self::assertLessThan(2.0, microtime(true) - $start, 'Nettoyage anormalement lent (expansion d\'entités ?).');
        self::assertStringNotContainsString('root:', $stored);
        self::assertStringNotContainsStringIgnoringCase('<!DOCTYPE', $stored);
        self::assertStringNotContainsStringIgnoringCase('<!ENTITY', $stored);
        foreach ($forbidden as $needle) {
            self::assertStringNotContainsStringIgnoringCase($needle, $stored);
        }
    }

    /**
     * Bombe de <use> : 10 niveaux de 10 → 10^5 instances au rendu. Le
     * nettoyeur coupe au-delà de son seuil d'instances rendues
     * (useThreshold) : les groupes l4 et l5 sont vidés. Écrit avec
     * `xlink:HrEf` en casse mixte, vecteur de CVE-2026-107381 (qui faisait
     * sauter ce contrôle en 0.22).
     */
    public function testNestedUseBombIsDefused(): void
    {
        $g = '<g id="l0"><circle r="1"/></g>';
        for ($i = 1; $i <= 5; ++$i) {
            $g .= '<g id="l'.$i.'">'.str_repeat('<use xlink:HrEf="#l'.($i - 1).'"/>', 10).'</g>';
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'.$g.'</svg>';

        $stored = $this->uploadSvg($svg);

        // Issue figée : un seuil devenu laxiste laisserait passer #l3 / #l4.
        self::assertSame(30, substr_count($stored, '<use'));
        self::assertStringNotContainsString('#l3', $stored);
        self::assertStringNotContainsString('#l4', $stored);
    }

    /**
     * Non-régression fonctionnelle : un SVG portant un DOCTYPE PUBLIC SVG 1.1
     * sans sous-ensemble interne (fréquent dans les fichiers produits par des
     * outils de dessin) reste accepté — seul le DOCTYPE est retiré. À noter :
     * les anciens exports Illustrator à sous-ensemble interne
     * (`<!ENTITY ns_svg …>` + `xmlns="&ns_svg;"`) sont désormais refusés.
     */
    public function testBenignSvgWithPublicDoctypeIsAccepted(): void
    {
        $stored = $this->uploadSvg(
            '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'
            .'<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'
            .'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4" fill="#2F7BF0"/></svg>'
        );

        self::assertStringContainsString('<circle', $stored);
        self::assertStringContainsString('#2F7BF0', $stored);
        self::assertStringNotContainsString('<!DOCTYPE', $stored);
    }

    public function testBenignSvgKeepsItsDrawing(): void
    {
        $stored = $this->uploadSvg(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4" fill="#2F7BF0"/></svg>'
        );

        self::assertStringContainsString('<circle', $stored);
        self::assertStringContainsString('#2F7BF0', $stored);
    }
}
