<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute user.locked_until / locked_reason / account_expires_at — verrouillage
 * temporaire (manuel ou automatique après échecs répétés, cf. LoginListener)
 * et expiration de compte, distincts de is_active.
 */
final class Version20260819121556 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute user.locked_until / locked_reason / account_expires_at — verrouillage et expiration de compte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD locked_until DATETIME DEFAULT NULL, ADD locked_reason VARCHAR(100) DEFAULT NULL, ADD account_expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP locked_until, DROP locked_reason, DROP account_expires_at');
    }
}
