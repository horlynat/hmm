<?php

namespace App\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Vérifie que chaque template d'erreur (résolu par TwigErrorRenderer via la
 * convention @Twig/Exception/error{code}.html.twig, cf.
 * vendor/symfony/twig-bridge/ErrorRenderer/TwigErrorRenderer.php) se rend
 * sans lever et affiche le texte attendu. La sélection du template selon le
 * code HTTP est la responsabilité de Symfony, pas de notre code — ce test ne
 * couvre donc que le contenu, pas l'algorithme de résolution.
 */
final class ErrorTemplatesRenderingTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function templateProvider(): iterable
    {
        yield '401' => ['@Twig/Exception/error401.html.twig', 'Authentification requise'];
        yield '402' => ['@Twig/Exception/error402.html.twig', 'Paiement requis'];
        yield '403' => ['@Twig/Exception/error403.html.twig', 'Accès refusé'];
        yield '404' => ['@Twig/Exception/error404.html.twig', 'Page introuvable'];
        yield '422' => ['@Twig/Exception/error422.html.twig', 'Requête invalide'];
        yield '500' => ['@Twig/Exception/error500.html.twig', 'Une erreur inattendue est survenue'];
    }

    #[DataProvider('templateProvider')]
    public function testErrorTemplateRendersWithExpectedCopy(string $template, string $expectedTitle): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get('twig');

        $html = $twig->render($template);

        $this->assertStringContainsString($expectedTitle, $html);
    }

    public function testFallbackTemplateRendersWithArbitraryStatusCode(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get('twig');

        $html = $twig->render('@Twig/Exception/error.html.twig', ['status_code' => 409]);

        $this->assertStringContainsString('409', $html);
    }
}
