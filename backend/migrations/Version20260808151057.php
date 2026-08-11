<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260808151057 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ai_assistant_conversation_log (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, ip_hash VARCHAR(64) NOT NULL, locale VARCHAR(5) NOT NULL, question_length INT NOT NULL, answer_length INT NOT NULL, chunk_ids_used JSON NOT NULL, model VARCHAR(50) DEFAULT NULL, gemini_tokens INT DEFAULT NULL, claude_tokens INT DEFAULT NULL, cost_usd NUMERIC(10, 6) DEFAULT NULL, latency_ms INT DEFAULT NULL, blocked TINYINT NOT NULL, block_reason VARCHAR(50) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE document_embedding (id INT AUTO_INCREMENT NOT NULL, entity_type VARCHAR(50) NOT NULL, entity_id INT NOT NULL, chunk_index INT NOT NULL, chunk_text LONGTEXT NOT NULL, chunk_summary LONGTEXT NOT NULL, embedding JSON NOT NULL, metadata JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_entity_chunk (entity_type, entity_id, chunk_index), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ai_assistant_settings ADD ai_assistant_enabled TINYINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE ai_assistant_conversation_log');
        $this->addSql('DROP TABLE document_embedding');
        $this->addSql('ALTER TABLE ai_assistant_settings DROP ai_assistant_enabled');
    }
}
