<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260628212123 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE login_history (id INT AUTO_INCREMENT NOT NULL, login_at DATETIME NOT NULL, ip VARCHAR(45) DEFAULT NULL, device VARCHAR(255) DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_37976E36A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE login_history ADD CONSTRAINT FK_37976E36A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user ADD created_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD password_changed_at DATETIME DEFAULT NULL, ADD phone VARCHAR(20) DEFAULT NULL, ADD is_two_factor_enabled TINYINT NOT NULL, CHANGE email email VARCHAR(180) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE login_history DROP FOREIGN KEY FK_37976E36A76ED395');
        $this->addSql('DROP TABLE login_history');
        $this->addSql('ALTER TABLE user DROP created_at, DROP updated_at, DROP password_changed_at, DROP phone, DROP is_two_factor_enabled, CHANGE email email VARCHAR(255) NOT NULL');
    }
}
