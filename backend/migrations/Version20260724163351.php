<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260724163351 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Projets: workflow de dépenses (catégorie/date/statut/approbation/justificatif) + tâches/jalons.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE project_task (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(255) DEFAULT \'todo\' NOT NULL, due_date DATE DEFAULT NULL, position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, project_id INT NOT NULL, assignee_id INT DEFAULT NULL, INDEX IDX_6BEF133D59EC7D60 (assignee_id), INDEX idx_project_task_project (project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE project_task ADD CONSTRAINT FK_6BEF133D166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_task ADD CONSTRAINT FK_6BEF133D59EC7D60 FOREIGN KEY (assignee_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_expenses ADD category VARCHAR(255) DEFAULT \'other\' NOT NULL, ADD spent_at DATE DEFAULT NULL, ADD status VARCHAR(255) DEFAULT \'pending\' NOT NULL, ADD approved_at DATETIME DEFAULT NULL, ADD receipt_path VARCHAR(255) DEFAULT NULL, ADD approved_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE project_expenses ADD CONSTRAINT FK_19D878802D234F6A FOREIGN KEY (approved_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_19D878802D234F6A ON project_expenses (approved_by_id)');

        // Les dépenses préexistantes étaient déjà comptées dans `project.spent` :
        // on les marque APPROUVÉES (et date effective = date de saisie) pour que le
        // recalcul (spent = somme des approuvées) préserve les montants actuels.
        $this->addSql('UPDATE project_expenses SET status = \'approved\', spent_at = DATE(created_at) WHERE status = \'pending\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_task DROP FOREIGN KEY FK_6BEF133D166D1F9C');
        $this->addSql('ALTER TABLE project_task DROP FOREIGN KEY FK_6BEF133D59EC7D60');
        $this->addSql('DROP TABLE project_task');
        $this->addSql('ALTER TABLE project_expenses DROP FOREIGN KEY FK_19D878802D234F6A');
        $this->addSql('DROP INDEX IDX_19D878802D234F6A ON project_expenses');
        $this->addSql('ALTER TABLE project_expenses DROP category, DROP spent_at, DROP status, DROP approved_at, DROP receipt_path, DROP approved_by_id');
    }
}
