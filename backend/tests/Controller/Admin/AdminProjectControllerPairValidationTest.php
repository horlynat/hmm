<?php

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\AdminProjectController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;

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

    /**
     * validateShowcasePairFields() — portée exacte, pas juste la détection
     * elle-même. Régression réelle : la version initiale appliquait le "|"
     * obligatoire à techStack ET results en plus de challenges — bloquait
     * alors la réédition du projet vitrine en prod, dont les 5 "résultats
     * concrets" sont tous des lignes à une seule partie (légitime, cf.
     * formatPairs()/parsePairs()). Ce test verrouille que seuls
     * challenges/challengesEn sont strictement vérifiés : results n'est même
     * plus interrogé, une ligne sans "|" ne doit jamais y déclencher d'erreur.
     */
    public function testOnlyChallengesFieldsAreStrictlyValidated(): void
    {
        $method = new \ReflectionMethod(AdminProjectController::class, 'validateShowcasePairFields');
        $controller = (new \ReflectionClass(AdminProjectController::class))->newInstanceWithoutConstructor();

        $challengesField = $this->createMock(FormInterface::class);
        $challengesField->method('getData')->willReturn('Défi correct | Solution correcte');
        $challengesField->expects(self::never())->method('addError');

        $challengesEnField = $this->createMock(FormInterface::class);
        $challengesEnField->method('getData')->willReturn('Challenge | Solution');
        $challengesEnField->expects(self::never())->method('addError');

        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('has')->with('challenges')->willReturn(true);
        // Seuls 'challenges' et 'challengesEn' doivent être demandés — un
        // appel avec 'results' ou 'techStack' ferait échouer ce mock
        // (aucune correspondance dans le map = valeur de retour null,
        // provoquant une TypeError sur getData()/addError() ensuite).
        $form->method('get')->willReturnMap([
            ['challenges', $challengesField],
            ['challengesEn', $challengesEnField],
        ]);

        $result = $method->invoke($controller, $form);

        self::assertTrue($result);
    }
}
