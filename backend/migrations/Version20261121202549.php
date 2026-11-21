<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Refonte 2026-11, lot R5 : textes de la page « À propos » ajoutés au
 * back-office (Vision / Mission / Ambition, ambition « D'ici trois ans »,
 * repères du parcours, encart « Toujours en formation »).
 *
 * Le contenu initial reprend le prototype de refonte validé par
 * l'utilisateur ; il reste modifiable dans Admin › Contenu › À propos. Il
 * n'est posé que sur la ligne existante (table à ligne unique), et
 * seulement là où le champ est encore vide — jamais par-dessus une saisie.
 *
 * NB : `doctrine:migrations:diff` proposait aussi DROP TABLE sessions et deux
 * changements de valeurs par défaut (course.type, system_setting) : écart
 * de schéma préexistant, sans rapport avec ce lot, volontairement exclu
 * (la table `sessions` sert au stockage des sessions PHP).
 */
final class Version20261121202549 extends AbstractMigration
{
    private const FIELDS = [
        'vision_pillar' => 'Une architecture bien pensée aujourd’hui évite les refontes coûteuses demain. Je conçois des systèmes qui grandissent avec l’activité de mes clients, sans dette technique cachée.',
        'mission_pillar' => 'Accompagner durablement la croissance de mes clients en leur livrant des solutions sur mesure, sécurisées et évolutives, de l’idée jusqu’à l’exploitation.',
        'ambition_pillar' => 'Contribuer, projet après projet, à ce que les organisations disposent d’outils numériques fiables, sûrs et durables, et accompagner leur croissance sur le long terme.',
        'horizon_mid_text' => 'Faire de la sécurité et de la gestion des risques un réflexe pour les PME d’Afrique centrale.',
        'learning_text' => 'Chaque projet m’amène à apprendre : nouvelles technologies, nouvelles menaces, nouvelles réglementations. Objectif à terme : un doctorat consacré à l’intelligence artificielle.',
    ];

    private const JOURNEY_STATS = [
        ['value' => 'Depuis 2016', 'label' => 'dans le numérique'],
        ['value' => 'Depuis 2018', 'label' => 'en gestion et management des risques'],
        ['value' => '2026', 'label' => 'fondation de Digital Business Group'],
    ];

    /** Versions anglaises (table `translation`, champs « xxxEn » transitoires). */
    private const FIELDS_EN = [
        'visionPillar' => 'A well-designed architecture today avoids costly rebuilds tomorrow. I design systems that grow with my clients’ business, with no hidden technical debt.',
        'missionPillar' => 'To support my clients’ growth over the long term with tailored, secure and scalable solutions, from the first idea to day-to-day operation.',
        'ambitionPillar' => 'To help organisations, project after project, get reliable, secure and lasting digital tools, and to support their growth over the long term.',
        'horizonMidText' => 'Make security and risk management a reflex for SMEs in Central Africa.',
        'learningText' => 'Every project teaches me something new: technologies, threats, regulations. Long-term goal: a doctorate in artificial intelligence.',
    ];

    private const JOURNEY_STATS_EN = [
        ['value' => 'Since 2016', 'label' => 'in digital'],
        ['value' => 'Since 2018', 'label' => 'in risk management'],
        ['value' => '2026', 'label' => 'founding of Digital Business Group'],
    ];

    public function getDescription(): string
    {
        return 'Refonte R5 : Vision/Mission/Ambition, horizon intermédiaire, repères du parcours et encart formation dans about_content';
    }

    public function up(Schema $schema): void
    {
        // Colonnes texte : MySQL remplit la ligne existante avec '' (valeur
        // implicite d'un TEXT NOT NULL). La colonne JSON est d'abord nullable,
        // remplie, puis rendue NOT NULL : on ne dépend pas de la valeur
        // implicite d'une colonne JSON.
        $this->addSql('ALTER TABLE about_content ADD vision_pillar LONGTEXT NOT NULL, ADD mission_pillar LONGTEXT NOT NULL, ADD ambition_pillar LONGTEXT NOT NULL, ADD horizon_mid_text LONGTEXT NOT NULL, ADD learning_text LONGTEXT NOT NULL, ADD journey_stats JSON DEFAULT NULL');

        foreach (self::FIELDS as $column => $text) {
            $this->addSql(\sprintf('UPDATE about_content SET %s = ? WHERE %1$s = \'\'', $column), [$text]);
        }
        $this->addSql('UPDATE about_content SET journey_stats = ? WHERE journey_stats IS NULL', [json_encode(self::JOURNEY_STATS, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)]);

        $this->addSql('ALTER TABLE about_content MODIFY journey_stats JSON NOT NULL');

        // Traductions anglaises : une ligne par champ, seulement s'il n'en
        // existe pas déjà une (jamais par-dessus une saisie).
        $en = self::FIELDS_EN + ['journeyStats' => json_encode(self::JOURNEY_STATS_EN, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)];
        foreach ($en as $field => $value) {
            $this->addSql(
                "INSERT INTO translation (entity_type, entity_id, field, locale, value)
                 SELECT 'AboutContent', a.id, ?, 'en', ? FROM about_content a
                 WHERE NOT EXISTS (SELECT 1 FROM translation t WHERE t.entity_type = 'AboutContent' AND t.entity_id = a.id AND t.field = ? AND t.locale = 'en')",
                [$field, $value, $field],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE about_content DROP vision_pillar, DROP mission_pillar, DROP ambition_pillar, DROP horizon_mid_text, DROP learning_text, DROP journey_stats');
        // Traductions anglaises éventuellement saisies pour ces champs.
        $this->addSql("DELETE FROM translation WHERE entity_type = 'AboutContent' AND field IN ('visionPillar', 'missionPillar', 'ambitionPillar', 'horizonMidText', 'learningText', 'journeyStats')");
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
