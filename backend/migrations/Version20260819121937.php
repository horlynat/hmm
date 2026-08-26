<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute user.is_system_account — distingue les comptes de service
 * (intégration/automatisation, créés via app:service-account:create) des
 * comptes humains, notamment pour les exclure des listes admin et des
 * emails de bienvenue.
 */
final class Version20260819121937 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute user.is_system_account — distingue les comptes de service des comptes humains';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD is_system_account TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP is_system_account');
    }
}
