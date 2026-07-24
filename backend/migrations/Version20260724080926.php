<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * User : ajout des codes de récupération 2FA (backup codes), stockés hachés.
 * Permettent de se connecter en cas de perte de l'appareil TOTP.
 */
final class Version20260724080926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'User : ajout de la colonne backup_codes (codes de récupération 2FA, hachés).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD backup_codes JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP backup_codes');
    }
}
