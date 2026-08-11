<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260810190903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute AboutContent.profileImagePath (photo de la section Bio, éditable depuis l'admin).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE about_content ADD profile_image_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE about_content DROP profile_image_path');
    }
}
