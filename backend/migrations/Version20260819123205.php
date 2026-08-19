<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Alignement cosmétique post-migration précédente : noms d'index générés à
 * la main (Version20260819130000) vs convention Doctrine réelle, et DEFAULT
 * superflu sur user.is_system_account (absent du mapping ORM). Aucun
 * changement de comportement.
 */
final class Version20260819123205 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Aligne les noms d'index de permission_definition et le DEFAULT de user.is_system_account sur le mapping Doctrine";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permission_definition RENAME INDEX idx_2c6467d5772c1bae TO IDX_2C16F585248673E9');
        $this->addSql('ALTER TABLE permission_definition RENAME INDEX idx_2c6467d5bcd57993 TO IDX_2C16F5857C0EA729');
        $this->addSql('ALTER TABLE user CHANGE is_system_account is_system_account TINYINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permission_definition RENAME INDEX idx_2c16f585248673e9 TO IDX_2C6467D5772C1BAE');
        $this->addSql('ALTER TABLE permission_definition RENAME INDEX idx_2c16f5857c0ea729 TO IDX_2C6467D5BCD57993');
        $this->addSql('ALTER TABLE user CHANGE is_system_account is_system_account TINYINT DEFAULT 0 NOT NULL');
    }
}
