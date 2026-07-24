<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723172938 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "ContactMessage : ajout de source/company/phone/channel/slot — distingue les flux publics (rendez-vous, candidature freelance) qui partagent cette entité générique, et sort le créneau/canal du texte libre.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE contact_message ADD source VARCHAR(100) NOT NULL DEFAULT 'Contact', ADD company VARCHAR(255) DEFAULT NULL, ADD phone VARCHAR(20) DEFAULT NULL, ADD channel VARCHAR(30) DEFAULT NULL, ADD slot VARCHAR(100) DEFAULT NULL, CHANGE status status VARCHAR(20) NOT NULL");
        $this->addSql("UPDATE contact_message SET source = CASE
            WHEN subject = 'Demande de rendez-vous' THEN 'Rendez-vous'
            WHEN subject = 'Candidature freelance' THEN 'Candidature freelance'
            ELSE 'Contact'
        END");
        $this->addSql('ALTER TABLE contact_message ALTER COLUMN source DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact_message DROP source, DROP company, DROP phone, DROP channel, DROP slot, CHANGE status status VARCHAR(20) DEFAULT \'nouveau\' NOT NULL');
    }
}
