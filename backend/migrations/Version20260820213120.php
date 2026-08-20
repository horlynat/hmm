<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute candidate_message : fil de conversation admin <-> candidat/collaborateur
 * (App\Entity\CandidateMessage). Colonne is_read (pas "read", mot réservé
 * MySQL — cf. commentaire sur CandidateMessage::$read). Les autres statements
 * produits par le diff automatique (table `sessions`, course.type,
 * system_setting.default_currency) relèvent d'une dérive de schéma locale
 * préexistante, sans rapport avec ce changement — volontairement exclus d'ici.
 */
final class Version20260820213120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute candidate_message (fil de conversation admin <-> candidat)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE candidate_message (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, from_admin TINYINT NOT NULL, is_read TINYINT NOT NULL, created_at DATETIME NOT NULL, candidate_id INT NOT NULL, INDEX IDX_396C9DDE91BD8781 (candidate_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE candidate_message ADD CONSTRAINT FK_396C9DDE91BD8781 FOREIGN KEY (candidate_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE candidate_message DROP FOREIGN KEY FK_396C9DDE91BD8781');
        $this->addSql('DROP TABLE candidate_message');
    }
}
