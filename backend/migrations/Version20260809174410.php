<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260809174410 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Devise d\'affichage par défaut sur SystemSetting (défaut USD)';
    }

    public function up(Schema $schema): void
    {
        // DEFAULT 'USD' explicite : la table system_setting a déjà une ligne
        // en prod (ligne unique, cf. SystemSettingRepository::getSettings()),
        // un ADD COLUMN NOT NULL sans défaut échouerait dessus.
        $this->addSql("ALTER TABLE system_setting ADD default_currency VARCHAR(10) NOT NULL DEFAULT 'USD'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE system_setting DROP default_currency');
    }
}
