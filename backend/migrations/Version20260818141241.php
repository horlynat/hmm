<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818141241 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute permission_definition : catalogue des permissions "métier" pilotables en base (cf. PermissionRegistry).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE permission_definition (
              id INT AUTO_INCREMENT NOT NULL,
              code VARCHAR(100) NOT NULL,
              label VARCHAR(255) NOT NULL,
              category VARCHAR(100) NOT NULL,
              default_role VARCHAR(30) NOT NULL,
              current_role VARCHAR(30) NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX IDX_2C16F585896DBBDE (updated_by_id),
              UNIQUE INDEX UNIQ_2C16F58577153098 (code),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              permission_definition
            ADD
              CONSTRAINT FK_2C16F585896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id) ON DELETE
            SET
              NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permission_definition DROP FOREIGN KEY FK_2C16F585896DBBDE');
        $this->addSql('DROP TABLE permission_definition');
    }
}
