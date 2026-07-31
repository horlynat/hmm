<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260731102849 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute HomeContent, AboutContent, AiAssistantSettings et AiAssistantEntry (contenu narratif bilingue piloté par le back-office).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE about_content (id INT AUTO_INCREMENT NOT NULL, hero_eyebrow VARCHAR(255) NOT NULL, hero_eyebrow_en VARCHAR(255) DEFAULT NULL, hero_title VARCHAR(255) NOT NULL, hero_title_en VARCHAR(255) DEFAULT NULL, hero_title_accent VARCHAR(255) NOT NULL, hero_title_accent_en VARCHAR(255) DEFAULT NULL, hero_sub LONGTEXT NOT NULL, hero_sub_en LONGTEXT DEFAULT NULL, profile_name VARCHAR(255) NOT NULL, profile_role VARCHAR(255) NOT NULL, profile_role_en VARCHAR(255) DEFAULT NULL, profile_availability VARCHAR(100) NOT NULL, profile_availability_en VARCHAR(100) DEFAULT NULL, profile_also VARCHAR(255) NOT NULL, profile_also_en VARCHAR(255) DEFAULT NULL, profile_location VARCHAR(255) NOT NULL, profile_location_en VARCHAR(255) DEFAULT NULL, profile_work_mode VARCHAR(255) NOT NULL, profile_work_mode_en VARCHAR(255) DEFAULT NULL, profile_languages VARCHAR(255) NOT NULL, profile_languages_en VARCHAR(255) DEFAULT NULL, bio_title VARCHAR(255) NOT NULL, bio_title_en VARCHAR(255) DEFAULT NULL, bio_p1 LONGTEXT NOT NULL, bio_p1_en LONGTEXT DEFAULT NULL, bio_p2 LONGTEXT NOT NULL, bio_p2_en LONGTEXT DEFAULT NULL, bio_p3 LONGTEXT NOT NULL, bio_p3_en LONGTEXT DEFAULT NULL, vision_title VARCHAR(255) NOT NULL, vision_title_en VARCHAR(255) DEFAULT NULL, vision_lede LONGTEXT NOT NULL, vision_lede_en LONGTEXT DEFAULT NULL, vision_today_text LONGTEXT NOT NULL, vision_today_text_en LONGTEXT DEFAULT NULL, vision_tomorrow_text LONGTEXT NOT NULL, vision_tomorrow_text_en LONGTEXT DEFAULT NULL, why1_title VARCHAR(255) NOT NULL, why1_title_en VARCHAR(255) DEFAULT NULL, why1_desc LONGTEXT NOT NULL, why1_desc_en LONGTEXT DEFAULT NULL, why2_title VARCHAR(255) NOT NULL, why2_title_en VARCHAR(255) DEFAULT NULL, why2_desc LONGTEXT NOT NULL, why2_desc_en LONGTEXT DEFAULT NULL, why3_title VARCHAR(255) NOT NULL, why3_title_en VARCHAR(255) DEFAULT NULL, why3_desc LONGTEXT NOT NULL, why3_desc_en LONGTEXT DEFAULT NULL, why4_title VARCHAR(255) NOT NULL, why4_title_en VARCHAR(255) DEFAULT NULL, why4_desc LONGTEXT NOT NULL, why4_desc_en LONGTEXT DEFAULT NULL, beyond_languages JSON NOT NULL, beyond_languages_en JSON DEFAULT NULL, beyond_interests JSON NOT NULL, beyond_interests_en JSON DEFAULT NULL, cta_title VARCHAR(255) NOT NULL, cta_title_en VARCHAR(255) DEFAULT NULL, cta_sub LONGTEXT NOT NULL, cta_sub_en LONGTEXT DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_by_id INT DEFAULT NULL, INDEX IDX_89D40788896DBBDE (updated_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ai_assistant_entry (id INT AUTO_INCREMENT NOT NULL, chip_label VARCHAR(100) NOT NULL, chip_label_en VARCHAR(100) DEFAULT NULL, keywords JSON NOT NULL, keywords_en JSON DEFAULT NULL, answer LONGTEXT NOT NULL, answer_en LONGTEXT DEFAULT NULL, sort_order INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ai_assistant_settings (id INT AUTO_INCREMENT NOT NULL, greeting LONGTEXT NOT NULL, greeting_en LONGTEXT DEFAULT NULL, fallback LONGTEXT NOT NULL, fallback_en LONGTEXT DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_by_id INT DEFAULT NULL, INDEX IDX_5F70524896DBBDE (updated_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE home_content (id INT AUTO_INCREMENT NOT NULL, hero_eyebrow VARCHAR(255) NOT NULL, hero_eyebrow_en VARCHAR(255) DEFAULT NULL, hero_title VARCHAR(255) NOT NULL, hero_title_en VARCHAR(255) DEFAULT NULL, hero_title_accent VARCHAR(255) NOT NULL, hero_title_accent_en VARCHAR(255) DEFAULT NULL, hero_sub LONGTEXT NOT NULL, hero_sub_en LONGTEXT DEFAULT NULL, hero_roles JSON NOT NULL, hero_roles_en JSON DEFAULT NULL, founder_badge VARCHAR(255) NOT NULL, founder_badge_en VARCHAR(255) DEFAULT NULL, diagram_caption VARCHAR(255) NOT NULL, diagram_caption_en VARCHAR(255) DEFAULT NULL, about_title VARCHAR(255) NOT NULL, about_title_en VARCHAR(255) DEFAULT NULL, about_p1 LONGTEXT NOT NULL, about_p1_en LONGTEXT DEFAULT NULL, about_p2 LONGTEXT NOT NULL, about_p2_en LONGTEXT DEFAULT NULL, about_highlight_title VARCHAR(255) NOT NULL, about_highlight_title_en VARCHAR(255) DEFAULT NULL, about_highlight_desc LONGTEXT NOT NULL, about_highlight_desc_en LONGTEXT DEFAULT NULL, about_vision_text LONGTEXT NOT NULL, about_vision_text_en LONGTEXT DEFAULT NULL, about_mission_text LONGTEXT NOT NULL, about_mission_text_en LONGTEXT DEFAULT NULL, freelance_title VARCHAR(255) NOT NULL, freelance_title_en VARCHAR(255) DEFAULT NULL, freelance_lede LONGTEXT NOT NULL, freelance_lede_en LONGTEXT DEFAULT NULL, freelance_point1 LONGTEXT NOT NULL, freelance_point1_en LONGTEXT DEFAULT NULL, freelance_point2 LONGTEXT NOT NULL, freelance_point2_en LONGTEXT DEFAULT NULL, freelance_point3 LONGTEXT NOT NULL, freelance_point3_en LONGTEXT DEFAULT NULL, freelance_card_desc LONGTEXT NOT NULL, freelance_card_desc_en LONGTEXT DEFAULT NULL, contact_cta_title VARCHAR(255) NOT NULL, contact_cta_title_en VARCHAR(255) DEFAULT NULL, contact_cta_sub LONGTEXT NOT NULL, contact_cta_sub_en LONGTEXT DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_by_id INT DEFAULT NULL, INDEX IDX_4BE5FBF1896DBBDE (updated_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE about_content ADD CONSTRAINT FK_89D40788896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ai_assistant_settings ADD CONSTRAINT FK_5F70524896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE home_content ADD CONSTRAINT FK_4BE5FBF1896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE about_content DROP FOREIGN KEY FK_89D40788896DBBDE');
        $this->addSql('ALTER TABLE ai_assistant_settings DROP FOREIGN KEY FK_5F70524896DBBDE');
        $this->addSql('ALTER TABLE home_content DROP FOREIGN KEY FK_4BE5FBF1896DBBDE');
        $this->addSql('DROP TABLE about_content');
        $this->addSql('DROP TABLE ai_assistant_entry');
        $this->addSql('DROP TABLE ai_assistant_settings');
        $this->addSql('DROP TABLE home_content');
    }
}
