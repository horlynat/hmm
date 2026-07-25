<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260725052054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project_comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, project_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_26A5E09F675F31B (author_id), INDEX idx_comment_project (project_id), INDEX idx_comment_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project_time_entry (id INT AUTO_INCREMENT NOT NULL, minutes INT NOT NULL, spent_on DATE NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, project_id INT NOT NULL, task_id INT DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_76F5073E8DB60186 (task_id), INDEX IDX_76F5073EA76ED395 (user_id), INDEX idx_time_entry_project (project_id), INDEX idx_time_entry_spent_on (spent_on), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE project_comment ADD CONSTRAINT FK_26A5E09166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_comment ADD CONSTRAINT FK_26A5E09F675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE project_time_entry ADD CONSTRAINT FK_76F5073E166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_time_entry ADD CONSTRAINT FK_76F5073E8DB60186 FOREIGN KEY (task_id) REFERENCES project_task (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_time_entry ADD CONSTRAINT FK_76F5073EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_comment DROP FOREIGN KEY FK_26A5E09166D1F9C');
        $this->addSql('ALTER TABLE project_comment DROP FOREIGN KEY FK_26A5E09F675F31B');
        $this->addSql('ALTER TABLE project_time_entry DROP FOREIGN KEY FK_76F5073E166D1F9C');
        $this->addSql('ALTER TABLE project_time_entry DROP FOREIGN KEY FK_76F5073E8DB60186');
        $this->addSql('ALTER TABLE project_time_entry DROP FOREIGN KEY FK_76F5073EA76ED395');
        $this->addSql('DROP TABLE project_comment');
        $this->addSql('DROP TABLE project_time_entry');
    }
}
