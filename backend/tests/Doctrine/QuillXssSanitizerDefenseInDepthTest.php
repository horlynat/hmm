<?php

namespace App\Tests\Doctrine;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Quill 2.0.2 ET 2.0.3 (code identique sur les deux tags upstream, vérifié) :
 * GHSA-v3m3-f69x-jf25 / CVE-2025-15056, AUCUN correctif publié
 * (`first_patched_version: null`). Épingler 2.0.2 n'aurait rien changé — on
 * garde 2.0.3 et on exclut les formats concernés de l'éditeur (cf.
 * assets/controllers/rich_text_controller.js : `formats` ne liste plus
 * 'formula'/'video').
 *
 * Ce test prouve le second filet, indépendant de Quill : même si un payload
 * de cette CVE atteignait tout de même `getSemanticHTML()` côté client
 * (bug amont, API Quill, ou régression du filtre `formats`), le sanitiseur
 * HTML serveur CONFIGURÉ EN PRODUCTION (config/packages/html_sanitizer.yaml,
 * pas un sanitizer de test minimal — cf. RichTextSanitizerListenerTest pour
 * la plomberie du listener) neutralise les deux payloads exacts de l'avis
 * avant toute persistance.
 */
final class QuillXssSanitizerDefenseInDepthTest extends KernelTestCase
{
    private function productionSanitizer(): HtmlSanitizerInterface
    {
        /** @var HtmlSanitizerInterface */
        return self::getContainer()->get('html_sanitizer.sanitizer.app.rich_text');
    }

    /**
     * Formula::html() (packages/quill/src/formats/formula.ts) :
     * `` `<span>${formula}</span>` `` — aucun échappement. PoC de l'avis :
     * la valeur ferme le span et injecte un <img onerror>.
     */
    public function testFormulaPayloadIsNeutralised(): void
    {
        $html = '<span></span><img src=x onerror=alert(1)></span>';

        $clean = $this->productionSanitizer()->sanitize($html);

        self::assertStringNotContainsString('onerror', $clean);
        self::assertStringNotContainsString('<script', $clean);
    }

    /**
     * Video::html() (packages/quill/src/formats/video.ts) :
     * `` `<a href="${video}">${video}</a>` `` — aucun échappement. PoC de
     * l'avis : l'URL casse l'attribut href et ajoute onmouseover.
     */
    public function testVideoPayloadIsNeutralised(): void
    {
        $url = 'https://example.com" onmouseover="alert(1)';
        $html = \sprintf('<a href="%s">%s</a>', $url, $url);

        $clean = $this->productionSanitizer()->sanitize($html);

        // Le libellé du lien reprend légitimement le texte littéral (Quill
        // place la même valeur dans l'attribut ET le contenu visible) — il
        // apparaît donc, mais entièrement encodé en texte inerte (`&#34;`,
        // `&#61;`), jamais comme un attribut vivant du <a>. Ce qui compte :
        // aucun attribut `onmouseover` réel sur la balise.
        self::assertMatchesRegularExpression('/^<a href="https:\/\/example\.com"[^>]*>/', $clean);
        self::assertDoesNotMatchRegularExpression('/<a[^>]*\bonmouseover\s*=/i', $clean, 'Aucun attribut onmouseover ne doit survivre sur la balise <a>.');
        self::assertStringContainsString('&#34; onmouseover&#61;&#34;alert(1)', $clean, 'Le texte littéral doit être présent mais entièrement échappé (texte inerte, pas un attribut).');
    }

    /** Non-régression : un lien légitime reste intact après ce même sanitiseur. */
    public function testLegitimateLinkSurvives(): void
    {
        $clean = $this->productionSanitizer()->sanitize('<p>Voir <a href="https://example.com">ce lien</a>.</p>');

        self::assertStringContainsString('href="https://example.com"', $clean);
        self::assertStringContainsString('ce lien', $clean);
    }
}
