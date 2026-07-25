<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723141345 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rend QuoteRequest.phone et QuoteRequest.user_id optionnels pour permettre les demandes de devis anonymes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE
              quote_request
            CHANGE
              phone phone VARCHAR(20) DEFAULT NULL,
            CHANGE
              user_id user_id INT DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE
              quote_request
            CHANGE
              phone phone VARCHAR(20) NOT NULL,
            CHANGE
              user_id user_id INT NOT NULL
        SQL);
    }
}
