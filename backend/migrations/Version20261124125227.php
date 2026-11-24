<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261124125227 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refonte : récit des études de cas (project_info), chapô et offre liée des articles, 5e différenciateur À propos';
    }

    public function up(Schema $schema): void
    {
        // Ajouts uniquement, tous facultatifs (NULL / défaut 0) : aucune ligne
        // existante n'est modifiée. Les écarts de schéma sans rapport détectés
        // par diff (table sessions, défauts course/system_setting) sont
        // volontairement exclus.
        $this->addSql('ALTER TABLE about_content ADD why5_title VARCHAR(255) DEFAULT NULL, ADD why5_desc LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD summary LONGTEXT DEFAULT NULL, ADD related_offer_slug VARCHAR(60) DEFAULT NULL');
        $this->addSql('ALTER TABLE project_info ADD result VARCHAR(255) DEFAULT NULL, ADD result_en VARCHAR(255) DEFAULT NULL, ADD context LONGTEXT DEFAULT NULL, ADD context_en LONGTEXT DEFAULT NULL, ADD stake LONGTEXT DEFAULT NULL, ADD stake_en LONGTEXT DEFAULT NULL, ADD solution LONGTEXT DEFAULT NULL, ADD solution_en LONGTEXT DEFAULT NULL, ADD brief JSON DEFAULT NULL, ADD brief_en JSON DEFAULT NULL, ADD steps JSON DEFAULT NULL, ADD steps_en JSON DEFAULT NULL, ADD deliverables JSON DEFAULT NULL, ADD deliverables_en JSON DEFAULT NULL, ADD gallery_captions JSON DEFAULT NULL, ADD gallery_captions_en JSON DEFAULT NULL, ADD discreet TINYINT DEFAULT 0 NOT NULL, ADD related_offer_slug VARCHAR(60) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE about_content DROP why5_title, DROP why5_desc');
        $this->addSql('ALTER TABLE article DROP summary, DROP related_offer_slug');
        $this->addSql('ALTER TABLE project_info DROP result, DROP result_en, DROP context, DROP context_en, DROP stake, DROP stake_en, DROP solution, DROP solution_en, DROP brief, DROP brief_en, DROP steps, DROP steps_en, DROP deliverables, DROP deliverables_en, DROP gallery_captions, DROP gallery_captions_en, DROP discreet, DROP related_offer_slug');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
