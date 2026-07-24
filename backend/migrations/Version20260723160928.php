<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723160928 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Structure QuoteRequest : catégorie métier, détail de qualification, source, budget, devise, délai, canal de contact, pièce jointe mentionnée et précisions IA — remplace le fourre-tout texte libre par un vrai schéma.';
    }

    public function up(Schema $schema): void
    {
        // Colonnes nullables/avec défaut pour ne pas casser les lignes existantes ;
        // category/channel restent obligatoires côté validation applicative (Assert\NotBlank).
        $this->addSql("ALTER TABLE quote_request ADD category VARCHAR(100) NOT NULL DEFAULT '', ADD category_detail VARCHAR(255) DEFAULT NULL, ADD source VARCHAR(100) DEFAULT NULL, ADD budget VARCHAR(150) DEFAULT NULL, ADD currency VARCHAR(10) DEFAULT NULL, ADD timeline VARCHAR(100) DEFAULT NULL, ADD channel VARCHAR(30) NOT NULL DEFAULT '', ADD attachment_name VARCHAR(255) DEFAULT NULL, ADD clarifications JSON DEFAULT NULL");
        $this->addSql("ALTER TABLE quote_request ALTER COLUMN category DROP DEFAULT, ALTER COLUMN channel DROP DEFAULT");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quote_request DROP category, DROP category_detail, DROP source, DROP budget, DROP currency, DROP timeline, DROP channel, DROP attachment_name, DROP clarifications');
    }
}
