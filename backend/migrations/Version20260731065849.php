<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260731065849 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les champs *_en (nullables) pour le contenu bilingue FR/EN : Article, Course, Experience, Project, ProjectInfo, Skill, SkillCategory, Testimonial.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article ADD title_en VARCHAR(255) DEFAULT NULL, ADD content_en LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE course ADD title_en VARCHAR(255) DEFAULT NULL, ADD description_en LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE experience ADD role_en VARCHAR(255) DEFAULT NULL, ADD description_en LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD title_en VARCHAR(255) DEFAULT NULL, ADD description_en LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE project_info ADD role_en VARCHAR(255) DEFAULT NULL, ADD objectives_en JSON DEFAULT NULL, ADD tech_stack_en JSON DEFAULT NULL, ADD challenges_en JSON DEFAULT NULL, ADD results_en JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE skill ADD name_en VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE skill_category ADD name_en VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE testimonial ADD content_en VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article DROP title_en, DROP content_en');
        $this->addSql('ALTER TABLE course DROP title_en, DROP description_en');
        $this->addSql('ALTER TABLE experience DROP role_en, DROP description_en');
        $this->addSql('ALTER TABLE project DROP title_en, DROP description_en');
        $this->addSql('ALTER TABLE project_info DROP role_en, DROP objectives_en, DROP tech_stack_en, DROP challenges_en, DROP results_en');
        $this->addSql('ALTER TABLE skill DROP name_en');
        $this->addSql('ALTER TABLE skill_category DROP name_en');
        $this->addSql('ALTER TABLE testimonial DROP content_en');
    }
}
