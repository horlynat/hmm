<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802211820 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project ADD source_quote_request_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE655D04B8 FOREIGN KEY (source_quote_request_id) REFERENCES quote_request (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2FB3D0EE655D04B8 ON project (source_quote_request_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE655D04B8');
        $this->addSql('DROP INDEX UNIQ_2FB3D0EE655D04B8 ON project');
        $this->addSql('ALTER TABLE project DROP source_quote_request_id');
    }
}
