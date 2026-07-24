<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723165523 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "QuoteRequest.status passe d'un tinyint nullable (null/1/0) à un enum pending/accepted/suspended/rejected — une demande acceptée ne redevient plus jamais refusée, elle peut seulement être suspendue puis reprise.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE quote_request ADD status_new VARCHAR(20) NOT NULL DEFAULT 'pending'");
        $this->addSql("UPDATE quote_request SET status_new = CASE
            WHEN status = 1 THEN 'accepted'
            WHEN status = 0 THEN 'rejected'
            ELSE 'pending'
        END");
        $this->addSql('ALTER TABLE quote_request DROP status');
        $this->addSql('ALTER TABLE quote_request CHANGE status_new status VARCHAR(20) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quote_request ADD status_old TINYINT DEFAULT NULL');
        $this->addSql("UPDATE quote_request SET status_old = CASE
            WHEN status IN ('accepted', 'suspended') THEN 1
            WHEN status = 'rejected' THEN 0
            ELSE NULL
        END");
        $this->addSql('ALTER TABLE quote_request DROP status');
        $this->addSql('ALTER TABLE quote_request CHANGE status_old status TINYINT DEFAULT NULL');
    }
}
