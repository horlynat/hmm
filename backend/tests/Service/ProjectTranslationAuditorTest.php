<?php

namespace App\Tests\Service;

use App\Entity\Project;
use App\Entity\ProjectInfo;
use App\Service\ProjectTranslationAuditor;
use PHPUnit\Framework\TestCase;

final class ProjectTranslationAuditorTest extends TestCase
{
    private function makeProject(string $description, ?string $descriptionEn): Project
    {
        $project = new Project();
        $project->setTitle('Titre');
        $project->setTitleEn('Title');
        $project->setDescription($description);
        $project->setDescriptionEn($descriptionEn);
        $project->setLink('https://example.com');
        $project->setBudget('0');

        return $project;
    }

    public function testFlagsMissingTranslation(): void
    {
        $project = $this->makeProject(str_repeat('Un contenu français assez long. ', 20), null);

        $issues = (new ProjectTranslationAuditor())->findIncompleteTranslations($project);

        self::assertArrayHasKey('Description', $issues);
    }

    public function testFlagsTruncatedTranslation(): void
    {
        // Reproduit le cas réel constaté en prod : l'anglais s'arrête à
        // mi-chemin plutôt que d'être totalement absent.
        $fr = str_repeat('Un contenu français assez long pour être significatif. ', 20);
        $en = substr($fr, 0, (int) (strlen($fr) * 0.3));

        $issues = (new ProjectTranslationAuditor())->findIncompleteTranslations($this->makeProject($fr, $en));

        self::assertArrayHasKey('Description', $issues);
    }

    public function testDoesNotFlagACompleteTranslation(): void
    {
        $fr = str_repeat('Un contenu français assez long. ', 20);
        $en = str_repeat('A reasonably long English content. ', 20);

        $issues = (new ProjectTranslationAuditor())->findIncompleteTranslations($this->makeProject($fr, $en));

        self::assertArrayNotHasKey('Description', $issues);
    }

    public function testDoesNotFlagAFieldWithNoFrenchContentToTranslate(): void
    {
        // Rien à traduire s'il n'y a rien en français au départ (ex. rôle
        // jamais renseigné) — ne doit jamais être signalé comme "incomplet",
        // à la différence d'un champ FR rempli mais jamais traduit.
        $project = $this->makeProject(
            str_repeat('Un contenu français assez long pour être significatif. ', 20),
            str_repeat('A reasonably long English content to match it. ', 20),
        );
        $info = new ProjectInfo();
        // role/roleEn restent tous deux null : rien à comparer, pas un "trou".
        $project->setInfo($info);

        $issues = (new ProjectTranslationAuditor())->findIncompleteTranslations($project);

        self::assertArrayNotHasKey('Votre rôle', $issues);
        self::assertArrayNotHasKey('Objectifs du projet', $issues);
    }

    public function testChecksProjectInfoShowcaseFields(): void
    {
        $project = $this->makeProject('Description FR suffisamment longue pour ne pas être ignorée.', 'A sufficiently long EN description to not be ignored.');
        $info = new ProjectInfo();
        $info->setChallenges([
            ['problem' => 'Un défi assez long pour être significatif dans ce test.', 'solution' => 'Une solution assez longue pour être significative dans ce test.'],
        ]);
        $info->setChallengesEn(null); // jamais traduit
        $project->setInfo($info);

        $issues = (new ProjectTranslationAuditor())->findIncompleteTranslations($project);

        self::assertArrayHasKey('Défis rencontrés & solutions', $issues);
        self::assertArrayNotHasKey('Description', $issues);
    }
}
