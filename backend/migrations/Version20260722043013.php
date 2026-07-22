<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260722043013 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le secret TOTP (2FA) sur user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD totp_secret VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP totp_secret');
    }
}
