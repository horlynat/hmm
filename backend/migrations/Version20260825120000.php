<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rend l'historique d'un projet (`project_history`) traçable au-delà de la
 * suppression du projet lui-même.
 *
 * Avant cette migration, supprimer un projet supprimait aussi (en cascade,
 * `ON DELETE CASCADE`) tout son `project_history` — y compris l'entrée
 * "project_deleted" qu'on venait d'y ajouter dans le même flush : la
 * suppression d'un projet ne laissait donc strictement aucune trace exploitable.
 *
 * - `project_history.project_id` devient nullable, avec `ON DELETE SET NULL`
 *   au lieu de `CASCADE` : les lignes survivent à la suppression du projet.
 * - `project_history.project_title` (nouvelle colonne) capture une copie figée
 *   du titre du projet au moment de l'action, indépendante de la relation —
 *   nécessaire puisque `project_id` peut désormais être NULL, et donc que le
 *   titre ne peut plus être lu via une jointure une fois le projet disparu.
 *
 * Voir aussi App\Entity\ProjectHistory, App\Entity\Project::addToHistory()
 * (retrait de orphanRemoval côté ORM — sans quoi Doctrine supprimait ces
 * lignes en cascade indépendamment de la contrainte SQL ci-dessous).
 */
final class Version20260825120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "project_history survit à la suppression du projet (project_id nullable + ON DELETE SET NULL, ajout de project_title)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_history ADD project_title VARCHAR(255) NOT NULL DEFAULT \'\'');
        $this->addSql('UPDATE project_history ph INNER JOIN project p ON p.id = ph.project_id SET ph.project_title = p.title');
        // Retire le DEFAULT '' — utile seulement le temps du ADD COLUMN (MySQL exige une
        // valeur pour remplir les lignes existantes) ; le mapping Doctrine n'en définit pas.
        $this->addSql('ALTER TABLE project_history CHANGE project_title project_title VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE project_history DROP FOREIGN KEY FK_B1A47C2E166D1F9C');
        $this->addSql('ALTER TABLE project_history CHANGE project_id project_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE project_history ADD CONSTRAINT FK_B1A47C2E166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_history DROP FOREIGN KEY FK_B1A47C2E166D1F9C');

        // Les entrées dont le projet a été supprimé depuis (project_id NULL) ne peuvent
        // pas être restaurées vers la contrainte NOT NULL d'origine — elles sont perdues
        // au rollback, comme documenté dans Version20260824004032::down() pour `translation`.
        $this->addSql('DELETE FROM project_history WHERE project_id IS NULL');

        $this->addSql('ALTER TABLE project_history CHANGE project_id project_id INT NOT NULL');
        $this->addSql('ALTER TABLE project_history ADD CONSTRAINT FK_B1A47C2E166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_history DROP project_title');
    }
}
