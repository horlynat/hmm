<?php

namespace App\Tests\Templates;

use App\Entity\Project;
use App\Entity\ProjectInfo;
use App\Form\ProjectType;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Garde-fou contre les régressions constatées en prod lors de la fusion des
 * formulaires création/édition de projet (_form_wizard.html.twig +
 * _form.html.twig -> _form.html.twig unique, cf. historique de ce fichier) :
 * un composant ou un champ absent de la nouvelle vue passe le lint Twig et
 * PHPStan sans broncher — seul un rendu réel révèle l'omission. Ce test
 * verrouille la présence des éléments déjà oubliés une fois, pour que ça ne
 * puisse plus se reproduire silencieusement à la prochaine réécriture de ce
 * template.
 *
 * Rendu direct du Twig (comme ErrorTemplatesRenderingTest), pas une requête
 * HTTP complète : plus rapide, et cible précisément la responsabilité du
 * template plutôt que tout le pipeline routing/sécurité/DB.
 */
final class AdminProjectFormRenderingTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{0: bool}>
     */
    public static function contextProvider(): iterable
    {
        yield 'création (projet neuf)' => [false];
        yield 'édition (projet existant, avec contenu vitrine)' => [true];
    }

    #[DataProvider('contextProvider')]
    public function testFormRendersAllExpectedElements(bool $isEditingExistingProject): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $project = new Project();
        $project->setTitle('Projet de test');
        $project->setDescription('Description de test');
        $project->setLink('https://example.com');
        $project->setBudget('100.00');

        $formOptions = ['include_showcase' => true];
        if (!$isEditingExistingProject) {
            $formOptions['include_planning'] = true;
        } else {
            // En édition, un ProjectInfo existant est le cas qui a révélé la
            // régression de départ (contenu vitrine invisible) — on le
            // reproduit ici plutôt que de tester seulement le cas vide.
            $info = new ProjectInfo();
            $info->setChallenges([['problem' => 'Défi test', 'solution' => 'Solution test']]);
            $project->setInfo($info);
        }

        $form = $container->get('form.factory')->create(ProjectType::class, $project, $formOptions);

        $html = $container->get('twig')->render('admin/project/_form.html.twig', [
            'project' => $project,
            'form' => $form->createView(),
            'button_label' => 'Enregistrer',
        ]);

        // 1) Sélecteur de langue de saisie — absent par oubli lors de la
        // fusion create/update (signalé en prod, cf. commit qui l'a réintégré).
        $this->assertStringContainsString(
            "Langue en cours d'édition",
            $html,
            'Le sélecteur de langue (LocaleToggle) doit toujours être présent — sans lui, plus de traduction automatique en direct.',
        );

        // 2) Les 3 onglets toujours présents (Planification en plus si création).
        $this->assertStringContainsString('data-tab="infos"', $html);
        $this->assertStringContainsString('data-tab="showcase"', $html);
        $this->assertStringContainsString('data-tab="media"', $html);
        if (!$isEditingExistingProject) {
            $this->assertStringContainsString('data-tab="planning"', $html);
        } else {
            $this->assertStringNotContainsString('data-tab="planning"', $html);
        }

        // 3) budget : ajouté sans condition dans ProjectType (contrairement à
        // priority/billingType/startedAt/deadline) — doit apparaître exactement
        // une fois. Un oubli de ce champ dans le template ne casse ni le lint
        // Twig ni PHPStan : form_end() le fait réapparaître tout seul, sans
        // style, en fin de page (constaté en prod) plutôt que de faire échouer
        // quoi que ce soit avant le rendu réel.
        $this->assertSame(
            1,
            substr_count($html, 'name="project[budget]"'),
            'form.budget doit être rendu exactement une fois, quel que soit le contexte (create/update).',
        );

        // 4) Image de couverture — champ réel (pas seulement la galerie
        // générique media[]) présent dans les deux contextes.
        $this->assertStringContainsString('name="project[coverImage]"', $html);
        $this->assertSame(
            1,
            substr_count($html, 'name="project[removeCoverImage]"'),
            "removeCoverImage doit être rendu exactement une fois (même masqué sans couverture existante) — sinon form_end() le fait réapparaître seul, sans style, en fin de formulaire.",
        );

        // 5) Aucun chemin d'image doublé (cf. correctif média_grid.html.twig /
        // member/project/read.html.twig — même bug de composition de chemin).
        $this->assertStringNotContainsString('/uploads/projects//uploads/projects/', $html);

        // 6) Repère de format visible et permanent sur "Défis & solutions" —
        // ajouté après que le format "Défi | Solution" par ligne ait été mal
        // rempli deux fois en prod malgré une aide déjà présente (mais trop
        // discrète : 11px à 40% d'opacité, et un placeholder qui disparaît
        // dès la première frappe). Doit rester visible même une fois le champ
        // rempli, contrairement au placeholder.
        $this->assertStringContainsString('Défi&nbsp;|&nbsp;Solution', $html);
        $this->assertStringContainsString('Challenge&nbsp;|&nbsp;Solution', $html);

        // 7) Contrôleur Stimulus de retour en direct (pendant la frappe, pas
        // seulement à la sauvegarde) sur challenges/challengesEn — même règle
        // que AdminProjectController::findLinesWithoutSeparator() côté
        // serveur, mais visible avant de cliquer sur "Enregistrer".
        $this->assertSame(
            2,
            substr_count($html, 'data-controller="pair-format-hint"'),
            'Le contrôleur de retour en direct doit être présent sur challenges ET challengesEn, nulle part ailleurs (le "|" y est obligatoire, pas sur techStack/results).',
        );
    }
}
