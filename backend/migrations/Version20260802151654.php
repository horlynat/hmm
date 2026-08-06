<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802151654 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table invoice (facturation client par projet).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE invoice (id INT AUTO_INCREMENT NOT NULL, number VARCHAR(50) NOT NULL, label VARCHAR(255) NOT NULL, amount NUMERIC(12, 2) NOT NULL, currency VARCHAR(10) NOT NULL, status VARCHAR(255) NOT NULL, issued_at DATETIME NOT NULL, due_date DATE DEFAULT NULL, paid_at DATETIME DEFAULT NULL, project_id INT NOT NULL, INDEX idx_invoice_project (project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744166D1F9C');
        $this->addSql('DROP TABLE invoice');
    }
}
