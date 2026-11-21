<?php

namespace App\Tests\Form\DataTransformer;

use App\Form\DataTransformer\OfferLinesTransformer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Mode « repères » (refonte R5, page À propos) : « Depuis 2016 | dans le
 * numérique » → {value, label}.
 */
final class OfferLinesTransformerStatTest extends TestCase
{
    public function testParsesOneStatPerLine(): void
    {
        $t = new OfferLinesTransformer(OfferLinesTransformer::MODE_STAT, false);

        self::assertSame(
            [['value' => 'Depuis 2016', 'label' => 'dans le numérique'], ['value' => '2026', 'label' => 'fondation | groupe']],
            $t->reverseTransform("  Depuis 2016 |  dans le numérique \r\n\n2026 | fondation | groupe"),
            'Espaces retirés, lignes vides ignorées ; seul le premier « | » sépare.',
        );
    }

    public function testRoundTripIsStable(): void
    {
        $t = new OfferLinesTransformer(OfferLinesTransformer::MODE_STAT, false);
        $rows = [['value' => 'Depuis 2018', 'label' => 'en gestion des risques'], ['value' => '2026', 'label' => 'fondation']];

        self::assertSame($rows, $t->reverseTransform($t->transform($rows)));
    }

    public function testEmptyInputFollowsNullability(): void
    {
        self::assertSame([], (new OfferLinesTransformer(OfferLinesTransformer::MODE_STAT, false))->reverseTransform("  \n "));
        self::assertNull((new OfferLinesTransformer(OfferLinesTransformer::MODE_STAT, true))->reverseTransform(''));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformed(): iterable
    {
        yield 'sans séparateur' => ['Depuis 2016 dans le numérique'];
        yield 'valeur vide' => [' | dans le numérique'];
        yield 'libellé vide' => ['Depuis 2016 |   '];
        yield 'UTF-8 invalide' => ["Depuis 2016 | x\xC3\x28"];
        yield 'texte énorme' => [str_repeat("a | b\n", 5000)];
    }

    #[DataProvider('malformed')]
    public function testMalformedLineIsAFormErrorNeverAnException(string $input): void
    {
        $this->expectException(TransformationFailedException::class);
        (new OfferLinesTransformer(OfferLinesTransformer::MODE_STAT, false))->reverseTransform($input);
    }

    public function testNonArrayModelRendersEmpty(): void
    {
        self::assertSame('', (new OfferLinesTransformer(OfferLinesTransformer::MODE_STAT, false))->transform(null));
    }
}
