<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260730165238 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project_info (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(255) DEFAULT NULL, objectives JSON NOT NULL, tech_stack JSON NOT NULL, challenges JSON NOT NULL, results JSON NOT NULL, repo_url VARCHAR(255) DEFAULT NULL, project_id INT NOT NULL, cover_image_id INT DEFAULT NULL, architecture_diagram_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_F218F94F166D1F9C (project_id), INDEX IDX_F218F94FE5A0E336 (cover_image_id), INDEX IDX_F218F94FCA34FA62 (architecture_diagram_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE project_info ADD CONSTRAINT FK_F218F94F166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_info ADD CONSTRAINT FK_F218F94FE5A0E336 FOREIGN KEY (cover_image_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_info ADD CONSTRAINT FK_F218F94FCA34FA62 FOREIGN KEY (architecture_diagram_id) REFERENCES media (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_info DROP FOREIGN KEY FK_F218F94F166D1F9C');
        $this->addSql('ALTER TABLE project_info DROP FOREIGN KEY FK_F218F94FE5A0E336');
        $this->addSql('ALTER TABLE project_info DROP FOREIGN KEY FK_F218F94FCA34FA62');
        $this->addSql('DROP TABLE project_info');
    }
}
