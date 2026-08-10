<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260810052419 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Retire document_embedding.embedding : plus de retrieval par similarité côté assistant IA, le corpus complet est envoyé tel quel à Claude (mis en cache).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_embedding DROP embedding');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_embedding ADD embedding JSON NOT NULL');
    }
}
