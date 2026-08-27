<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260827071407 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Crée la table incident (journal d'incidents, cf. App\\Entity\\Incident).";
    }

    public function up(Schema $schema): void
    {
        // Isolé à la main du diff auto-généré : celui-ci incluait aussi du
        // drift local sans rapport (sessions/course/system_setting), déjà
        // couvert par d'autres migrations existantes — ne jamais le rejouer
        // ici.
        $this->addSql('CREATE TABLE incident (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, category VARCHAR(30) NOT NULL, severity VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, description LONGTEXT NOT NULL, root_cause LONGTEXT DEFAULT NULL, remediation LONGTEXT DEFAULT NULL, related_reference VARCHAR(255) DEFAULT NULL, detected_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, reported_by_id INT DEFAULT NULL, INDEX IDX_3D03A11A71CE806 (reported_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE incident ADD CONSTRAINT FK_3D03A11A71CE806 FOREIGN KEY (reported_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE incident DROP FOREIGN KEY FK_3D03A11A71CE806');
        $this->addSql('DROP TABLE incident');
    }
}
