<?php

namespace App\Service;

use App\Entity\Project;

/**
 * Détecte une traduction anglaise incomplète (vide, ou nettement plus courte
 * que son équivalent français) sur un projet — pas d'appel externe, juste une
 * comparaison de longueur ; ne vérifie donc jamais l'EXACTITUDE d'une
 * traduction, seulement sa complétude apparente.
 *
 * Existe suite à un cas réel constaté en prod : la description anglaise du
 * projet vitrine s'arrêtait à mi-chemin (résultat, semble-t-il, d'une
 * traduction jamais terminée avant l'ajout du sélecteur de langue/traduction
 * en direct) — restée invisible pendant un temps indéterminé, personne ne la
 * consultant en anglais. Ce contrôle rend le problème visible côté admin
 * (avant publication) plutôt que découvert par un visiteur anglophone.
 */
final class ProjectTranslationAuditor
{
    /**
     * En dessous de ce ratio (longueur EN / longueur FR), la traduction est
     * considérée comme suspecte. 0.5 plutôt que plus strict : certaines
     * langues sont légitimement plus compactes que d'autres — l'objectif est
     * d'attraper une traduction clairement tronquée ou oubliée, pas de
     * pénaliser un anglais simplement plus concis.
     */
    private const MIN_RATIO = 0.5;

    /**
     * @return array<string, array{fr: int, en: int}> libellé du champ => longueurs comparées, uniquement pour les champs suspects
     */
    public function findIncompleteTranslations(Project $project): array
    {
        $pairs = [
            'Titre' => [$project->getTitle(), $project->getTitleEn()],
            'Description' => [$project->getDescription(), $project->getDescriptionEn()],
        ];

        if ($info = $project->getInfo()) {
            $pairs['Votre rôle'] = [$info->getRole(), $info->getRoleEn()];
            $pairs['Objectifs du projet'] = [$info->getObjectives(), $info->getObjectivesEn()];
            $pairs['Stack technique'] = [$info->getTechStack(), $info->getTechStackEn()];
            $pairs['Défis rencontrés & solutions'] = [$info->getChallenges(), $info->getChallengesEn()];
            $pairs['Résultats concrets'] = [$info->getResults(), $info->getResultsEn()];
        }

        $issues = [];
        foreach ($pairs as $label => [$fr, $en]) {
            $frLength = $this->length($fr);
            if (0 === $frLength) {
                continue; // rien en français, rien à comparer
            }

            $enLength = $this->length($en);
            if ($enLength < $frLength * self::MIN_RATIO) {
                $issues[$label] = ['fr' => $frLength, 'en' => $enLength];
            }
        }

        return $issues;
    }

    private function length(mixed $value): int
    {
        if (null === $value || [] === $value) {
            return 0;
        }
        if (is_array($value)) {
            return strlen(json_encode($value, JSON_UNESCAPED_UNICODE) ?: '');
        }

        return strlen((string) $value);
    }
}
