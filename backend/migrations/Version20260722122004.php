<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260722122004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les tables du module Paramètres : system_setting, notification_preference, integration.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE integration (
              id INT AUTO_INCREMENT NOT NULL,
              type VARCHAR(20) NOT NULL,
              name VARCHAR(100) NOT NULL,
              webhook_url VARCHAR(500) DEFAULT NULL,
              api_key_encrypted LONGTEXT DEFAULT NULL,
              config JSON DEFAULT NULL,
              is_active TINYINT NOT NULL,
              last_tested_at DATETIME DEFAULT NULL,
              last_test_success TINYINT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE notification_preference (
              id INT AUTO_INCREMENT NOT NULL,
              priority VARCHAR(20) NOT NULL,
              email_enabled TINYINT NOT NULL,
              push_enabled TINYINT NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              UNIQUE INDEX UNIQ_NOTIFICATION_PRIORITY (priority),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE system_setting (
              id INT AUTO_INCREMENT NOT NULL,
              site_name VARCHAR(100) NOT NULL,
              logo_path VARCHAR(255) DEFAULT NULL,
              primary_color VARCHAR(7) NOT NULL,
              theme VARCHAR(20) NOT NULL,
              default_locale VARCHAR(10) NOT NULL,
              available_locales JSON NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX IDX_7307C40B896DBBDE (updated_by_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              system_setting
            ADD
              CONSTRAINT FK_7307C40B896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id) ON DELETE
            SET
              NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE system_setting DROP FOREIGN KEY FK_7307C40B896DBBDE');
        $this->addSql('DROP TABLE integration');
        $this->addSql('DROP TABLE notification_preference');
        $this->addSql('DROP TABLE system_setting');
    }
}
