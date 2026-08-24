<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Introduit une table de traduction générique (`translation`) qui remplace
 * les colonnes "xxxEn" dupliquées sur HomeContent, AboutContent, Project et
 * Article. Le français reste natif sur chaque entité (source de vérité,
 * toujours présente) ; seule la traduction anglaise passe désormais par
 * cette table — cf. App\Entity\Translation, App\Repository\
 * TranslationRepository, App\EventSubscriber\TranslationHydrationListener.
 *
 * Migre les données existantes (56 colonnes concernées, dont certaines
 * remplies) AVANT de les supprimer — jamais de perte de contenu déjà traduit.
 *
 * Remarque : `doctrine:migrations:diff` avait aussi détecté une dérive de
 * schéma sans rapport avec ce chantier (table `sessions` — stockage de
 * session Symfony natif, pas une entité Doctrine ; colonnes `course.type` et
 * `system_setting.default_currency`) — volontairement exclue d'ici, à traiter
 * séparément si besoin.
 */
final class Version20260824004032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table de traduction générique (translation) en remplacement des colonnes xxxEn de HomeContent/AboutContent/Project/Article';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE translation (
              id INT AUTO_INCREMENT NOT NULL,
              entity_type VARCHAR(100) NOT NULL,
              entity_id INT NOT NULL,
              field VARCHAR(100) NOT NULL,
              locale VARCHAR(5) NOT NULL,
              value LONGTEXT DEFAULT NULL,
              INDEX idx_translation_entity (entity_type, entity_id),
              UNIQUE INDEX uniq_translation_target (entity_type, entity_id, field, locale),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);

        // --- Copie des données existantes vers `translation` (avant suppression des colonnes) ---
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'heroEyebrow', 'en', hero_eyebrow_en FROM home_content WHERE hero_eyebrow_en IS NOT NULL AND hero_eyebrow_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'heroTitle', 'en', hero_title_en FROM home_content WHERE hero_title_en IS NOT NULL AND hero_title_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'heroTitleAccent', 'en', hero_title_accent_en FROM home_content WHERE hero_title_accent_en IS NOT NULL AND hero_title_accent_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'heroSub', 'en', hero_sub_en FROM home_content WHERE hero_sub_en IS NOT NULL AND hero_sub_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'heroRoles', 'en', hero_roles_en FROM home_content WHERE hero_roles_en IS NOT NULL AND hero_roles_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'founderBadge', 'en', founder_badge_en FROM home_content WHERE founder_badge_en IS NOT NULL AND founder_badge_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'diagramCaption', 'en', diagram_caption_en FROM home_content WHERE diagram_caption_en IS NOT NULL AND diagram_caption_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'aboutTitle', 'en', about_title_en FROM home_content WHERE about_title_en IS NOT NULL AND about_title_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'aboutP1', 'en', about_p1_en FROM home_content WHERE about_p1_en IS NOT NULL AND about_p1_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'aboutP2', 'en', about_p2_en FROM home_content WHERE about_p2_en IS NOT NULL AND about_p2_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'aboutHighlightTitle', 'en', about_highlight_title_en FROM home_content WHERE about_highlight_title_en IS NOT NULL AND about_highlight_title_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'aboutHighlightDesc', 'en', about_highlight_desc_en FROM home_content WHERE about_highlight_desc_en IS NOT NULL AND about_highlight_desc_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'aboutVisionText', 'en', about_vision_text_en FROM home_content WHERE about_vision_text_en IS NOT NULL AND about_vision_text_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'aboutMissionText', 'en', about_mission_text_en FROM home_content WHERE about_mission_text_en IS NOT NULL AND about_mission_text_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'freelanceTitle', 'en', freelance_title_en FROM home_content WHERE freelance_title_en IS NOT NULL AND freelance_title_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'freelanceLede', 'en', freelance_lede_en FROM home_content WHERE freelance_lede_en IS NOT NULL AND freelance_lede_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'freelancePoint1', 'en', freelance_point1_en FROM home_content WHERE freelance_point1_en IS NOT NULL AND freelance_point1_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'freelancePoint2', 'en', freelance_point2_en FROM home_content WHERE freelance_point2_en IS NOT NULL AND freelance_point2_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'freelancePoint3', 'en', freelance_point3_en FROM home_content WHERE freelance_point3_en IS NOT NULL AND freelance_point3_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'freelanceCardDesc', 'en', freelance_card_desc_en FROM home_content WHERE freelance_card_desc_en IS NOT NULL AND freelance_card_desc_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'contactCtaTitle', 'en', contact_cta_title_en FROM home_content WHERE contact_cta_title_en IS NOT NULL AND contact_cta_title_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'HomeContent', id, 'contactCtaSub', 'en', contact_cta_sub_en FROM home_content WHERE contact_cta_sub_en IS NOT NULL AND contact_cta_sub_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'heroEyebrow', 'en', hero_eyebrow_en FROM about_content WHERE hero_eyebrow_en IS NOT NULL AND hero_eyebrow_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'heroTitle', 'en', hero_title_en FROM about_content WHERE hero_title_en IS NOT NULL AND hero_title_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'heroTitleAccent', 'en', hero_title_accent_en FROM about_content WHERE hero_title_accent_en IS NOT NULL AND hero_title_accent_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'heroSub', 'en', hero_sub_en FROM about_content WHERE hero_sub_en IS NOT NULL AND hero_sub_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'profileRole', 'en', profile_role_en FROM about_content WHERE profile_role_en IS NOT NULL AND profile_role_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'profileAvailability', 'en', profile_availability_en FROM about_content WHERE profile_availability_en IS NOT NULL AND profile_availability_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'profileAlso', 'en', profile_also_en FROM about_content WHERE profile_also_en IS NOT NULL AND profile_also_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'profileLocation', 'en', profile_location_en FROM about_content WHERE profile_location_en IS NOT NULL AND profile_location_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'profileWorkMode', 'en', profile_work_mode_en FROM about_content WHERE profile_work_mode_en IS NOT NULL AND profile_work_mode_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'profileLanguages', 'en', profile_languages_en FROM about_content WHERE profile_languages_en IS NOT NULL AND profile_languages_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'bioTitle', 'en', bio_title_en FROM about_content WHERE bio_title_en IS NOT NULL AND bio_title_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'bioP1', 'en', bio_p1_en FROM about_content WHERE bio_p1_en IS NOT NULL AND bio_p1_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'bioP2', 'en', bio_p2_en FROM about_content WHERE bio_p2_en IS NOT NULL AND bio_p2_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'bioP3', 'en', bio_p3_en FROM about_content WHERE bio_p3_en IS NOT NULL AND bio_p3_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'visionTitle', 'en', vision_title_en FROM about_content WHERE vision_title_en IS NOT NULL AND vision_title_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'visionLede', 'en', vision_lede_en FROM about_content WHERE vision_lede_en IS NOT NULL AND vision_lede_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'visionTodayText', 'en', vision_today_text_en FROM about_content WHERE vision_today_text_en IS NOT NULL AND vision_today_text_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'visionTomorrowText', 'en', vision_tomorrow_text_en FROM about_content WHERE vision_tomorrow_text_en IS NOT NULL AND vision_tomorrow_text_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'why1Title', 'en', why1_title_en FROM about_content WHERE why1_title_en IS NOT NULL AND why1_title_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'why1Desc', 'en', why1_desc_en FROM about_content WHERE why1_desc_en IS NOT NULL AND why1_desc_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'why2Title', 'en', why2_title_en FROM about_content WHERE why2_title_en IS NOT NULL AND why2_title_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'why2Desc', 'en', why2_desc_en FROM about_content WHERE why2_desc_en IS NOT NULL AND why2_desc_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'why3Title', 'en', why3_title_en FROM about_content WHERE why3_title_en IS NOT NULL AND why3_title_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'why3Desc', 'en', why3_desc_en FROM about_content WHERE why3_desc_en IS NOT NULL AND why3_desc_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'why4Title', 'en', why4_title_en FROM about_content WHERE why4_title_en IS NOT NULL AND why4_title_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'why4Desc', 'en', why4_desc_en FROM about_content WHERE why4_desc_en IS NOT NULL AND why4_desc_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'beyondLanguages', 'en', beyond_languages_en FROM about_content WHERE beyond_languages_en IS NOT NULL AND beyond_languages_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'beyondInterests', 'en', beyond_interests_en FROM about_content WHERE beyond_interests_en IS NOT NULL AND beyond_interests_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'ctaTitle', 'en', cta_title_en FROM about_content WHERE cta_title_en IS NOT NULL AND cta_title_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'AboutContent', id, 'ctaSub', 'en', cta_sub_en FROM about_content WHERE cta_sub_en IS NOT NULL AND cta_sub_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'Project', id, 'title', 'en', title_en FROM project WHERE title_en IS NOT NULL AND title_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'Project', id, 'description', 'en', description_en FROM project WHERE description_en IS NOT NULL AND description_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'Article', id, 'title', 'en', title_en FROM article WHERE title_en IS NOT NULL AND title_en <> ''");
        $this->addSql("INSERT INTO translation (entity_type, entity_id, field, locale, value) SELECT 'Article', id, 'content', 'en', content_en FROM article WHERE content_en IS NOT NULL AND content_en <> ''");

        // --- Suppression des colonnes xxxEn (les données sont désormais dans `translation`) ---
        $this->addSql(<<<'SQL'
            ALTER TABLE home_content
              DROP hero_eyebrow_en, DROP hero_title_en, DROP hero_title_accent_en,
              DROP hero_sub_en, DROP hero_roles_en, DROP founder_badge_en,
              DROP diagram_caption_en, DROP about_title_en, DROP about_p1_en,
              DROP about_p2_en, DROP about_highlight_title_en, DROP about_highlight_desc_en,
              DROP about_vision_text_en, DROP about_mission_text_en, DROP freelance_title_en,
              DROP freelance_lede_en, DROP freelance_point1_en, DROP freelance_point2_en,
              DROP freelance_point3_en, DROP freelance_card_desc_en, DROP contact_cta_title_en,
              DROP contact_cta_sub_en
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE about_content
              DROP hero_eyebrow_en, DROP hero_title_en, DROP hero_title_accent_en,
              DROP hero_sub_en, DROP profile_role_en, DROP profile_availability_en,
              DROP profile_also_en, DROP profile_location_en, DROP profile_work_mode_en,
              DROP profile_languages_en, DROP bio_title_en, DROP bio_p1_en,
              DROP bio_p2_en, DROP bio_p3_en, DROP vision_title_en,
              DROP vision_lede_en, DROP vision_today_text_en, DROP vision_tomorrow_text_en,
              DROP why1_title_en, DROP why1_desc_en, DROP why2_title_en,
              DROP why2_desc_en, DROP why3_title_en, DROP why3_desc_en,
              DROP why4_title_en, DROP why4_desc_en, DROP beyond_languages_en,
              DROP beyond_interests_en, DROP cta_title_en, DROP cta_sub_en
        SQL);
        $this->addSql('ALTER TABLE project DROP title_en, DROP description_en');
        $this->addSql('ALTER TABLE article DROP title_en, DROP content_en');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE home_content
              ADD hero_eyebrow_en VARCHAR(255) DEFAULT NULL, ADD hero_title_en VARCHAR(255) DEFAULT NULL,
              ADD hero_title_accent_en VARCHAR(255) DEFAULT NULL, ADD hero_sub_en LONGTEXT DEFAULT NULL,
              ADD hero_roles_en JSON DEFAULT NULL, ADD founder_badge_en VARCHAR(255) DEFAULT NULL,
              ADD diagram_caption_en VARCHAR(255) DEFAULT NULL, ADD about_title_en VARCHAR(255) DEFAULT NULL,
              ADD about_p1_en LONGTEXT DEFAULT NULL, ADD about_p2_en LONGTEXT DEFAULT NULL,
              ADD about_highlight_title_en VARCHAR(255) DEFAULT NULL, ADD about_highlight_desc_en LONGTEXT DEFAULT NULL,
              ADD about_vision_text_en LONGTEXT DEFAULT NULL, ADD about_mission_text_en LONGTEXT DEFAULT NULL,
              ADD freelance_title_en VARCHAR(255) DEFAULT NULL, ADD freelance_lede_en LONGTEXT DEFAULT NULL,
              ADD freelance_point1_en LONGTEXT DEFAULT NULL, ADD freelance_point2_en LONGTEXT DEFAULT NULL,
              ADD freelance_point3_en LONGTEXT DEFAULT NULL, ADD freelance_card_desc_en LONGTEXT DEFAULT NULL,
              ADD contact_cta_title_en VARCHAR(255) DEFAULT NULL, ADD contact_cta_sub_en LONGTEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE about_content
              ADD hero_eyebrow_en VARCHAR(255) DEFAULT NULL, ADD hero_title_en VARCHAR(255) DEFAULT NULL,
              ADD hero_title_accent_en VARCHAR(255) DEFAULT NULL, ADD hero_sub_en LONGTEXT DEFAULT NULL,
              ADD profile_role_en VARCHAR(255) DEFAULT NULL, ADD profile_availability_en VARCHAR(100) DEFAULT NULL,
              ADD profile_also_en VARCHAR(255) DEFAULT NULL, ADD profile_location_en VARCHAR(255) DEFAULT NULL,
              ADD profile_work_mode_en VARCHAR(255) DEFAULT NULL, ADD profile_languages_en VARCHAR(255) DEFAULT NULL,
              ADD bio_title_en VARCHAR(255) DEFAULT NULL, ADD bio_p1_en LONGTEXT DEFAULT NULL,
              ADD bio_p2_en LONGTEXT DEFAULT NULL, ADD bio_p3_en LONGTEXT DEFAULT NULL,
              ADD vision_title_en VARCHAR(255) DEFAULT NULL, ADD vision_lede_en LONGTEXT DEFAULT NULL,
              ADD vision_today_text_en LONGTEXT DEFAULT NULL, ADD vision_tomorrow_text_en LONGTEXT DEFAULT NULL,
              ADD why1_title_en VARCHAR(255) DEFAULT NULL, ADD why1_desc_en LONGTEXT DEFAULT NULL,
              ADD why2_title_en VARCHAR(255) DEFAULT NULL, ADD why2_desc_en LONGTEXT DEFAULT NULL,
              ADD why3_title_en VARCHAR(255) DEFAULT NULL, ADD why3_desc_en LONGTEXT DEFAULT NULL,
              ADD why4_title_en VARCHAR(255) DEFAULT NULL, ADD why4_desc_en LONGTEXT DEFAULT NULL,
              ADD beyond_languages_en JSON DEFAULT NULL, ADD beyond_interests_en JSON DEFAULT NULL,
              ADD cta_title_en VARCHAR(255) DEFAULT NULL, ADD cta_sub_en LONGTEXT DEFAULT NULL
        SQL);
        $this->addSql('ALTER TABLE project ADD title_en VARCHAR(255) DEFAULT NULL, ADD description_en LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD title_en VARCHAR(255) DEFAULT NULL, ADD content_en LONGTEXT DEFAULT NULL');

        // Rollback schéma uniquement : ne réinjecte pas le contenu de
        // `translation` dans les colonnes recréées (nécessiterait une UPDATE
        // par champ, 56 au total, pour un chemin qui n'est en pratique
        // presque jamais exécuté). Exporter `translation` avant de lancer ce
        // down() si son contenu doit être préservé.
        $this->addSql('DROP TABLE translation');
    }
}
