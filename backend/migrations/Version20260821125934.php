<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260821125934 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute project_join_request (demandes d\'auto-association freelance en attente de validation admin)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE project_join_request (
              id INT AUTO_INCREMENT NOT NULL,
              status VARCHAR(20) NOT NULL,
              requested_at DATETIME NOT NULL,
              decided_at DATETIME DEFAULT NULL,
              project_id INT NOT NULL,
              user_id INT NOT NULL,
              decided_by_id INT DEFAULT NULL,
              INDEX IDX_C395009C166D1F9C (project_id),
              INDEX IDX_C395009CA76ED395 (user_id),
              INDEX IDX_C395009CE26B496B (decided_by_id),
              INDEX idx_join_request_project_status (project_id, status),
              UNIQUE INDEX uniq_active_join_request (project_id, user_id, status),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              project_join_request
            ADD
              CONSTRAINT FK_C395009C166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              project_join_request
            ADD
              CONSTRAINT FK_C395009CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              project_join_request
            ADD
              CONSTRAINT FK_C395009CE26B496B FOREIGN KEY (decided_by_id) REFERENCES user (id) ON DELETE
            SET
              NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_join_request DROP FOREIGN KEY FK_C395009C166D1F9C');
        $this->addSql('ALTER TABLE project_join_request DROP FOREIGN KEY FK_C395009CA76ED395');
        $this->addSql('ALTER TABLE project_join_request DROP FOREIGN KEY FK_C395009CE26B496B');
        $this->addSql('DROP TABLE project_join_request');
    }
}
