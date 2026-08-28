<?php

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\AdminProjectController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verrouille findLinesWithoutSeparator() — le garde-fou ajouté après un cas
 * réel constaté DEUX FOIS en prod sur le même champ ("Défis rencontrés &
 * solutions") : un admin qui édite ce texte comme un paragraphe normal
 * (au lieu du micro-format "Défi | Solution" par ligne) obtient un
 * enregistrement qui semble réussir, mais une fiche publique où "Solution"
 * reste silencieusement vide à chaque ligne. Ce test appelle la méthode
 * privée par réflexion — c'est une fonction pure sans dépendance, une
 * extraction en service dédié n'aurait rien apporté de plus ici.
 */
final class AdminProjectControllerPairValidationTest extends TestCase
{
    /** @return string[] */
    private function findLinesWithoutSeparator(string $raw): array
    {
        $method = new \ReflectionMethod(AdminProjectController::class, 'findLinesWithoutSeparator');

        /** @var AdminProjectController $controller */
        $controller = (new \ReflectionClass(AdminProjectController::class))->newInstanceWithoutConstructor();

        /** @var string[] */
        return $method->invoke($controller, $raw);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function malformedInputProvider(): iterable
    {
        yield 'un seul défi, aucun séparateur (cas réel constaté en prod)' => [
            "Un site vitrine et un outil de gestion dans le même produit : backend Symfony, API Platform et frontend Next.js.",
        ];
        yield 'plusieurs lignes, toutes sans séparateur' => [
            "Premier défi sans séparateur\nDeuxième défi sans séparateur non plus",
        ];
        yield 'une ligne correcte, une ligne fautive' => [
            "Défi correct | Solution correcte\nDéfi sans séparateur",
        ];
    }

    #[DataProvider('malformedInputProvider')]
    public function testDetectsLinesMissingTheSeparator(string $raw): void
    {
        self::assertNotSame([], $this->findLinesWithoutSeparator($raw));
    }

    public function testAcceptsProperlyFormattedPairs(): void
    {
        $raw = "Défi un | Solution un\nDéfi deux | Solution deux";

        self::assertSame([], $this->findLinesWithoutSeparator($raw));
    }

    public function testIgnoresEmptyLines(): void
    {
        $raw = "Défi un | Solution un\n\n   \nDéfi deux | Solution deux";

        self::assertSame([], $this->findLinesWithoutSeparator($raw));
    }

    public function testEmptyInputHasNoOffenders(): void
    {
        self::assertSame([], $this->findLinesWithoutSeparator(''));
    }
}
