<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Suppression des témoignages (décision du propriétaire, 2026-11-26) :
 * affichés nulle part sur le site, mais exposés par l'API (lecture ET envoi
 * public). Retire la table, les médias qui y étaient rattachés (lignes
 * seulement : les fichiers éventuels restent sur le disque) et les
 * permissions TESTIMONIAL_* du catalogue.
 */
final class Version20261126130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppression des témoignages (table, médias rattachés, permissions).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM media WHERE testimonial_id IS NOT NULL');
        $this->addSql('ALTER TABLE media DROP FOREIGN KEY FK_6A2CA10C1D4EC6B1');
        $this->addSql('DROP INDEX IDX_6A2CA10C1D4EC6B1 ON media');
        $this->addSql('ALTER TABLE media DROP testimonial_id');
        $this->addSql('DROP TABLE testimonial');
        $this->addSql("DELETE FROM permission_definition WHERE code LIKE 'TESTIMONIAL\\_%'");
    }

    public function down(Schema $schema): void
    {
        // Structure seulement : les témoignages supprimés ne sont pas restaurés.
        $this->addSql('CREATE TABLE testimonial (id INT AUTO_INCREMENT NOT NULL, author VARCHAR(255) NOT NULL, content VARCHAR(255) NOT NULL, rating NUMERIC(10, 0) DEFAULT NULL, published_at DATETIME DEFAULT NULL, content_en VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE media ADD testimonial_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_6A2CA10C1D4EC6B1 ON media (testimonial_id)');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT FK_6A2CA10C1D4EC6B1 FOREIGN KEY (testimonial_id) REFERENCES testimonial (id)');
    }
}
