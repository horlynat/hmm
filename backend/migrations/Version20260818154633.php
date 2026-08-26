<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818154633 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute user.super_admin_elevated_until — PAM, fenêtre d\'élévation temporaire (cf. User::isSuperAdminElevated()).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD super_admin_elevated_until DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP super_admin_elevated_until');
    }
}
