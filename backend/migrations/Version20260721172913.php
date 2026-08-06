<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260721172913 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE failed_login_attempt (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, ip VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, reason VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, INDEX idx_failed_login_ip (ip), INDEX idx_failed_login_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE failed_login_attempt');
    }
}
