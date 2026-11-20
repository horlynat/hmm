<?php

namespace App\Tests\Twig;

use App\Twig\AppExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

final class AppExtensionInitialsTest extends TestCase
{
    /** @return iterable<string, array{0: ?string, 1: string}> */
    public static function names(): iterable
    {
        yield 'prénom nom' => ['Jean-Paul Dupont', 'JD'];
        yield 'un seul mot' => ['bob', 'B'];
        yield 'email' => ['alice.martin@example.com', 'AM'];
        yield 'accents' => ['élodie ünal', 'ÉÜ'];
        // Un seul "mot" au sens du splitter (aucun espace/point/tiret/underscore) :
        // seule la première lettre réellement alphanumérique est retenue — la
        // charge ne peut de toute façon produire que \p{L}|\p{N}, jamais de
        // métacaractère HTML.
        yield 'charge XSS' => ["x');alert(1);//", 'X'];
        yield 'vide' => ['', '?'];
        yield 'null' => [null, '?'];
        yield 'ponctuation seule' => ['---', '?'];
    }

    #[DataProvider('names')]
    public function testInitials(?string $name, string $expected): void
    {
        $extension = new AppExtension($this->createStub(HtmlSanitizerInterface::class));

        self::assertSame($expected, $extension->initials($name));
    }
}
