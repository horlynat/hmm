<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260810143154 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute Course.type (diplome/certification/formation) pour classer les qualifications côté public.";
    }

    public function up(Schema $schema): void
    {
        // Défaut 'diplome' pour ne pas casser les lignes existantes — reste en
        // place comme filet de sécurité (le formulaire admin CourseType fixe
        // toujours explicitement une valeur à la création).
        $this->addSql("ALTER TABLE course ADD type VARCHAR(20) NOT NULL DEFAULT 'diplome'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course DROP type');
    }
}
