<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260704055132 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE media CHANGE alt_text alt_text VARCHAR(255) DEFAULT NULL, CHANGE type type VARCHAR(50) DEFAULT NULL, CHANGE article_id article_id INT DEFAULT NULL, CHANGE project_id project_id INT DEFAULT NULL, CHANGE testimonial_id testimonial_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE media CHANGE alt_text alt_text VARCHAR(255) NOT NULL, CHANGE type type VARCHAR(50) NOT NULL, CHANGE article_id article_id INT NOT NULL, CHANGE project_id project_id INT NOT NULL, CHANGE testimonial_id testimonial_id INT NOT NULL');
    }
}
