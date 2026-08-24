<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Complète automatiquement les champs anglais ("xxxEn") d'une entité de
 * contenu bilingue à partir de leur pendant français ("xxx"), via Claude —
 * pour qu'écrire en français suffise et que la version anglaise ne se
 * contente plus de retomber silencieusement sur le français quand le champ
 * anglais est resté vide (cf. `pickLocalized` côté frontend, qui fait
 * exactement ce repli — voulu comme filet de sécurité, pas comme mode de
 * fonctionnement normal).
 *
 * Détection par réflexion via App\Service\BilingualFieldReflector (partagée
 * avec App\Repository\TranslationRepository) — pas de liste de champs à
 * maintenir à la main.
 *
 * Deux cas déclenchent une (re)traduction :
 * - le champ anglais est vide alors que le français ne l'est pas ;
 * - le français a changé depuis la dernière valeur connue en base ET
 *   l'anglais n'a lui pas été modifié dans cette même soumission (sinon on
 *   écraserait une correction manuelle volontaire de l'anglais).
 */
final class ContentAutoTranslator
{
    public function __construct(
        private readonly ClaudeClient $claudeClient,
        private readonly BilingualFieldReflector $reflector,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $originalData snapshot des valeurs AVANT
     *                                           application du formulaire —
     *                                           typiquement
     *                                           `$entityManager->getUnitOfWork()->getOriginalEntityData($entity)`,
     *                                           un tableau vide pour une
     *                                           entité neuve (tout est alors
     *                                           traité comme "changé"). Depuis
     *                                           que les champs "xxxEn" ne sont
     *                                           plus des colonnes Doctrine
     *                                           (cf. Translation), Doctrine ne
     *                                           les suit plus ici — seul le
     *                                           cas "anglais vide" reste
     *                                           couvert par ce filet ; le "FR a
     *                                           changé, EN pas retouché" est
     *                                           désormais couvert en direct par
     *                                           bilingual_field_controller.js.
     *
     * @return string[] noms des champs anglais effectivement (re)traduits — pour un message de statut
     */
    public function syncTranslations(object $entity, array $originalData = []): array
    {
        $pairs = $this->reflector->discoverPairs($entity);
        if (!$pairs) {
            return [];
        }

        $toTranslate = [];
        foreach ($pairs as $base => $pair) {
            $frValue = $pair['frGetter']->invoke($entity);
            $enValue = $pair['enGetter']->invoke($entity);

            $frChanged = !array_key_exists($base, $originalData) || $originalData[$base] !== $frValue;
            $enChanged = !array_key_exists($base.'En', $originalData) || $originalData[$base.'En'] !== $enValue;

            if ($this->reflector->isBlank($enValue) && !$this->reflector->isBlank($frValue)) {
                $toTranslate[$base] = $frValue;
            } elseif ($frChanged && !$enChanged && !$this->reflector->isBlank($frValue)) {
                $toTranslate[$base] = $frValue;
            }
        }

        if (!$toTranslate) {
            return [];
        }

        try {
            $translated = $this->translateBatch($toTranslate);
        } catch (\Throwable $e) {
            // Échec de traduction : on ne bloque JAMAIS l'enregistrement du
            // contenu français pour ça — c'est la source de vérité. On logue
            // et l'appelant informe l'admin (champs à traduire manuellement).
            $this->logger->error('ContentAutoTranslator : échec de traduction, contenu français enregistré sans traduction anglaise.', [
                'entity' => $entity::class,
                'fields' => array_keys($toTranslate),
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $filled = [];
        foreach ($translated as $base => $value) {
            if (!isset($pairs[$base])) {
                continue; // Claude a renvoyé une clé imprévue : ignorée plutôt que planter.
            }
            $pairs[$base]['enSetter']->invoke($entity, $value);
            $filled[] = $base.'En';
        }

        return $filled;
    }

    /**
     * @param array<string, string|array<int, string>> $entries clé -> valeur française
     *
     * @return array<string, string|array<int, string>> clé -> valeur anglaise traduite
     */
    private function translateBatch(array $entries): array
    {
        $system = <<<'PROMPT'
            Tu traduis des contenus éditoriaux du français vers l'anglais, pour le
            site professionnel d'un développeur full-stack (aussi consultant
            cybersécurité et technicien assurance) basé à Brazzaville, Congo. Ton
            direct et professionnel, pas de familiarité.

            Règles strictes :
            - Si une valeur d'entrée est un tableau de chaînes, réponds avec un
              tableau de même longueur, dans le même ordre, chaque élément traduit
              individuellement.
            - Ne reformule pas, ne raccourcis pas, ne développe pas : une
              traduction fidèle, pas une réécriture éditoriale.
            - Conserve la mise en forme éventuelle (retours à la ligne, listes).

            Réponds UNIQUEMENT avec un objet JSON valide : {"clé": "valeur
            traduite", ...}, exactement les mêmes clés que l'entrée, chaque valeur
            du même type (string ou tableau de strings) que la valeur d'entrée
            correspondante. Aucun texte avant ou après le JSON.
            PROMPT;

        $result = $this->claudeClient->ask(
            $system,
            [],
            json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            true,
            4096,
        );

        $decoded = json_decode($result['text'], true);
        if (!is_array($decoded) && preg_match('/\{.*\}/s', $result['text'], $matches)) {
            $decoded = json_decode($matches[0], true);
        }
        if (!is_array($decoded)) {
            throw new \RuntimeException('Réponse de traduction non-JSON : '.$result['text']);
        }

        return $decoded;
    }
}
