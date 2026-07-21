<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260710133628 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project_expenses (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(12, 2) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, project_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_19D87880A76ED395 (user_id), INDEX idx_project_expense_project (project_id), INDEX idx_project_expense_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE project_expenses ADD CONSTRAINT FK_19D87880166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_expenses ADD CONSTRAINT FK_19D87880A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE project_expense DROP FOREIGN KEY `FK_BB2481C3166D1F9C`');
        $this->addSql('ALTER TABLE project_expense DROP FOREIGN KEY `FK_BB2481C3A76ED395`');
        $this->addSql('DROP TABLE project_expense');
        $this->addSql('ALTER TABLE project_history DROP FOREIGN KEY `FK_B1A47C2E166D1F9C`');
        $this->addSql('ALTER TABLE project_history CHANGE action action VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE project_history ADD CONSTRAINT FK_B1A47C2E166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_project_history_created_at ON project_history (created_at)');
        $this->addSql('ALTER TABLE project_history RENAME INDEX idx_b1a47c2e166d1f9c TO idx_project_history_project');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project_expense (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(12, 2) NOT NULL, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, created_at DATETIME NOT NULL, project_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_BB2481C3166D1F9C (project_id), INDEX IDX_BB2481C3A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE project_expense ADD CONSTRAINT `FK_BB2481C3166D1F9C` FOREIGN KEY (project_id) REFERENCES project (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE project_expense ADD CONSTRAINT `FK_BB2481C3A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE project_expenses DROP FOREIGN KEY FK_19D87880166D1F9C');
        $this->addSql('ALTER TABLE project_expenses DROP FOREIGN KEY FK_19D87880A76ED395');
        $this->addSql('DROP TABLE project_expenses');
        $this->addSql('ALTER TABLE project_history DROP FOREIGN KEY FK_B1A47C2E166D1F9C');
        $this->addSql('DROP INDEX idx_project_history_created_at ON project_history');
        $this->addSql('ALTER TABLE project_history CHANGE action action VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE project_history ADD CONSTRAINT `FK_B1A47C2E166D1F9C` FOREIGN KEY (project_id) REFERENCES project (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE project_history RENAME INDEX idx_project_history_project TO IDX_B1A47C2E166D1F9C');
    }
}
