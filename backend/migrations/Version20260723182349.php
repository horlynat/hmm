<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723182349 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "User : ajout des champs profil collaborateur (specialties, availability, portfolioUrl, bio) — support de l'inscription publique freelance.";
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD specialties JSON DEFAULT NULL, ADD availability VARCHAR(100) DEFAULT NULL, ADD portfolio_url VARCHAR(255) DEFAULT NULL, ADD bio LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP specialties, DROP availability, DROP portfolio_url, DROP bio');
    }
}
