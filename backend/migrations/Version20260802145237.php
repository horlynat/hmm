<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802145237 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute QuoteRequest.createdAt (nullable) : ce champ n'existait pas avant, "
            . "les demandes existantes restent donc à NULL plutôt qu'une date de migration fictive.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quote_request ADD created_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quote_request DROP created_at');
    }
}
